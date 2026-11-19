<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\MateriBab;
use App\Models\MateriItem;
use App\Services\KompresGambar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MateriController extends Controller
{
    /* ---------- Daftar bab per kelas ---------- */

    public function index(Kelas $kelas)
    {
        $this->milikKelas($kelas);
        $babs = $kelas->materiBabs()->with('mapel')->withCount('items')->get();
        $mapels = auth()->user()->mapels()->orderBy('nama')->get();

        // penyimpanan terpakai (semua kelas milik guru ini)
        $terpakai = (int) MateriItem::whereHas('bab.kelas', fn ($q) => $q->where('guru_id', auth()->id()))
            ->sum('ukuran');

        return view('materi.index', compact('kelas', 'babs', 'mapels', 'terpakai'));
    }

    public function store(Request $request, Kelas $kelas)
    {
        $this->milikKelas($kelas);
        $data = $this->validasiBab($request);
        $bab = $kelas->materiBabs()->create(array_merge($data, [
            'mapel_id' => $this->mapelValid($data['mapel_id'] ?? null),
            'urutan' => ((int) MateriBab::where('kelas_id', $kelas->id)->max('urutan')) + 1,
        ]));

        return redirect()->route('materi.edit', $bab)->with('ok', 'Bab dibuat. Sekarang tambahkan materinya.');
    }

    public function kodeBaru(Kelas $kelas)
    {
        $this->milikKelas($kelas);
        $kelas->forceFill(['kode_publik' => Str::lower(Str::random(8))])->save();

        return back()->with('ok', 'Kode kelas diganti. Link dan QR lama tidak berlaku lagi.');
    }

    /* ---------- Satu bab ---------- */

    public function edit(MateriBab $bab)
    {
        $this->milikBab($bab);
        $bab->load('items', 'kelas', 'mapel');
        $mapels = auth()->user()->mapels()->orderBy('nama')->get();

        return view('materi.edit', compact('bab', 'mapels'));
    }

    public function update(Request $request, MateriBab $bab)
    {
        $this->milikBab($bab);
        $data = $this->validasiBab($request);
        $bab->update(array_merge($data, ['mapel_id' => $this->mapelValid($data['mapel_id'] ?? null)]));

        return back()->with('ok', 'Bab disimpan.');
    }

    public function destroy(MateriBab $bab)
    {
        $this->milikBab($bab);
        $kelasId = $bab->kelas_id;

        foreach ($bab->items as $item) {
            $this->hapusBerkas($item);
        }

        $bab->delete();

        return redirect()->route('materi.index', $kelasId)->with('ok', 'Bab dihapus.');
    }

    public function pindahBab(Request $request, MateriBab $bab)
    {
        $this->milikBab($bab);
        $request->validate(['arah' => 'required|in:naik,turun']);
        $tetangga = MateriBab::where('kelas_id', $bab->kelas_id)
            ->when($bab->mapel_id, fn ($q) => $q->where('mapel_id', $bab->mapel_id), fn ($q) => $q->whereNull('mapel_id'));
        $this->tukar($bab, $request->input('arah'), $tetangga);

        return back();
    }

    /* ---------- Item materi ---------- */

    public function itemStore(Request $request, MateriBab $bab)
    {
        $this->milikBab($bab);
        $tipe = $request->validate(['tipe' => 'required|in:file,link,teks'])['tipe'];
        $urutan = ((int) MateriItem::where('bab_id', $bab->id)->max('urutan')) + 1;

        if ($tipe === 'file') {
            $request->validate([
                'judul' => 'required|string|max:150',
                'berkas' => 'required|file|max:20480|mimes:pdf,ppt,pptx,doc,docx,xls,xlsx,jpg,jpeg,png,webp,gif',
            ]);
            $berkas = $request->file('berkas');
            $ext = strtolower($berkas->getClientOriginalExtension());
            $path = $berkas->storeAs('materi/'.$bab->kelas_id, Str::random(24).'.'.$ext, 'local');

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                KompresGambar::proses(Storage::disk('local')->path($path));
            }

            $bab->items()->create([
                'tipe' => 'file',
                'judul' => $request->input('judul'),
                'path' => $path,
                'nama_asli' => $berkas->getClientOriginalName(),
                'ekstensi' => $ext,
                'ukuran' => Storage::disk('local')->size($path),
                'urutan' => $urutan,
            ]);
        } elseif ($tipe === 'link') {
            $v = $request->validate([
                'judul' => 'required|string|max:150',
                'url' => 'required|url:http,https|max:500',
            ]);
            $item = new MateriItem(['url' => $v['url']]);
            $bab->items()->create([
                'tipe' => $item->embed ? 'video' : 'link',
                'judul' => $v['judul'],
                'url' => $v['url'],
                'urutan' => $urutan,
            ]);
        } else {
            $v = $request->validate([
                'judul' => 'required|string|max:150',
                'isi' => 'required|string|max:20000',
            ]);
            $bab->items()->create([
                'tipe' => 'teks',
                'judul' => $v['judul'],
                'isi' => $v['isi'],
                'urutan' => $urutan,
            ]);
        }

        return back()->with('ok', 'Materi ditambahkan.');
    }

    public function itemDestroy(MateriItem $item)
    {
        $this->milikBab($item->bab);
        $this->hapusBerkas($item);
        $item->delete();

        return back()->with('ok', 'Materi dihapus.');
    }

    public function itemPindah(Request $request, MateriItem $item)
    {
        $this->milikBab($item->bab);
        $request->validate(['arah' => 'required|in:naik,turun']);
        $this->tukar($item, $request->input('arah'), MateriItem::where('bab_id', $item->bab_id));

        return back();
    }

    /* ---------- Bantu ---------- */

    private function validasiBab(Request $request): array
    {
        return $request->validate([
            'judul' => 'required|string|max:120',
            'mapel_id' => 'nullable|integer',
            'deskripsi' => 'nullable|string|max:1000',
            'status' => 'required|in:draft,terbit',
            'terbit_pada' => 'nullable|date',
        ]);
    }

    // mapel hanya sah jika milik guru yang login
    private function mapelValid($id): ?int
    {
        return $id ? auth()->user()->mapels()->whereKey($id)->value('id') : null;
    }

    // tukar urutan dengan tetangga, sekaligus merapikan nomor urut 0..n
    private function tukar($model, string $arah, $query): void
    {
        $daftar = $query->orderBy('urutan')->orderBy('id')->get()->all();
        $i = null;

        foreach ($daftar as $n => $m) {
            if ($m->id === $model->id) {
                $i = $n;
            }
        }

        $j = $arah === 'naik' ? ($i ?? 0) - 1 : ($i ?? 0) + 1;

        if ($i === null || ! isset($daftar[$j])) {
            return;
        }

        [$daftar[$i], $daftar[$j]] = [$daftar[$j], $daftar[$i]];

        foreach ($daftar as $n => $m) {
            if ((int) $m->urutan !== $n) {
                $m->update(['urutan' => $n]);
            }
        }
    }

    private function hapusBerkas(MateriItem $item): void
    {
        if ($item->path) {
            Storage::disk('local')->delete($item->path);
        }
    }

    private function milikBab(MateriBab $bab): void
    {
        abort_unless($bab->kelas->guru_id === auth()->id(), 403);
    }

    private function milikKelas(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}