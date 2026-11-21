<?php

namespace App\Http\Controllers;

use App\Models\Soal;
use App\Services\KompresGambar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SoalController extends Controller
{
    public function index(Request $request)
    {
        $mapels = auth()->user()->mapels()->orderBy('nama')->get();

        $soals = Soal::where('guru_id', auth()->id())
            ->with('mapel')
            ->withCount('ujians')
            ->when($request->filled('mapel_id'), fn ($q) => $q->where('mapel_id', (int) $request->query('mapel_id')))
            ->when(in_array($request->query('tipe'), ['pg', 'isian', 'esai'], true), fn ($q) => $q->where('tipe', $request->query('tipe')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($request->query('q'))).'%';
                $q->where('teks', 'like', $like);
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('soal.index', compact('soals', 'mapels'));
    }

    public function create()
    {
        return view('soal.form', [
            'soal' => new Soal(['tipe' => 'pg', 'bobot' => 1]),
            'mapels' => auth()->user()->mapels()->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);

        if ($request->hasFile('gambar')) {
            $data['gambar'] = $this->simpanGambar($request);
        }

        Soal::create($data + ['guru_id' => auth()->id()]);

        return redirect()->route('soal.index')->with('ok', 'Soal disimpan.');
    }

    public function edit(Soal $soal)
    {
        $this->milik($soal);

        return view('soal.form', [
            'soal' => $soal,
            'mapels' => auth()->user()->mapels()->orderBy('nama')->get(),
            'terkunci' => $soal->sudahDikerjakan(),
        ]);
    }

    public function update(Request $request, Soal $soal)
    {
        $this->milik($soal);

        if ($soal->sudahDikerjakan()) {
            return back()->withErrors([
                'teks' => 'Soal ini sudah dikerjakan siswa dan tidak bisa diubah. Gunakan "Salin" untuk membuat versi baru.',
            ]);
        }

        $data = $this->validasi($request);

        if ($request->hasFile('gambar')) {
            $this->hapusGambar($soal);
            $data['gambar'] = $this->simpanGambar($request);
        } elseif ($request->boolean('hapus_gambar')) {
            $this->hapusGambar($soal);
            $data['gambar'] = null;
        }

        $soal->update($data);

        return redirect()->route('soal.index')->with('ok', 'Soal diperbarui.');
    }

    public function destroy(Soal $soal)
    {
        $this->milik($soal);

        if ($soal->ujians()->exists()) {
            return back()->withErrors([
                'hapus' => 'Soal ini dipakai di paket ujian. Lepaskan dulu dari ujian tersebut.',
            ]);
        }

        $this->hapusGambar($soal);
        $soal->delete();

        return redirect()->route('soal.index')->with('ok', 'Soal dihapus.');
    }

    // Salin soal (tanpa gambar dijadikan file baru)
    public function salin(Soal $soal)
    {
        $this->milik($soal);

        $baru = $soal->replicate();

        if ($soal->gambar && Storage::disk('local')->exists($soal->gambar)) {
            $path = 'soal/'.Str::random(24).'.'.pathinfo($soal->gambar, PATHINFO_EXTENSION);
            Storage::disk('local')->copy($soal->gambar, $path);
            $baru->gambar = $path;
        }

        $baru->save();

        return redirect()->route('soal.edit', $baru)->with('ok', 'Soal disalin. Silakan ubah versi barunya.');
    }

    // Pratinjau gambar untuk guru
    public function gambar(Soal $soal)
    {
        $this->milik($soal);

        abort_unless($soal->gambar && Storage::disk('local')->exists($soal->gambar), 404);

        return response()->file(Storage::disk('local')->path($soal->gambar));
    }

    private function validasi(Request $request): array
    {
        $d = $request->validate([
            'tipe' => 'required|in:pg,isian,esai',
            'mapel_id' => 'nullable|integer',
            'teks' => 'required|string|max:5000',
            'bobot' => 'required|integer|min:1|max:100',
            'opsi' => 'nullable|array',
            'opsi.*' => 'nullable|string|max:500',
            'kunci_pg' => 'nullable|in:A,B,C,D,E',
            'kunci_isian' => 'nullable|string|max:500',
            'kunci_esai' => 'nullable|string|max:2000',
            'gambar' => 'nullable|image|max:5120|mimes:jpg,jpeg,png,webp,gif',
        ]);

        $mapelId = ($d['mapel_id'] ?? null)
            ? auth()->user()->mapels()->whereKey($d['mapel_id'])->value('id')
            : null;

        $hasil = [
            'tipe' => $d['tipe'],
            'mapel_id' => $mapelId,
            'teks' => $d['teks'],
            'bobot' => $d['bobot'],
            'opsi' => null,
            'kunci' => null,
        ];

        if ($d['tipe'] === 'pg') {
            $opsi = collect($d['opsi'] ?? [])
                ->map(fn ($t) => trim((string) $t))
                ->filter(fn ($t) => $t !== '')
                ->all();

            if (count($opsi) < 2) {
                throw ValidationException::withMessages([
                    'opsi' => 'Isi minimal dua pilihan jawaban.',
                ]);
            }

            if (empty($d['kunci_pg']) || ! isset($opsi[$d['kunci_pg']])) {
                throw ValidationException::withMessages([
                    'kunci_pg' => 'Pilih jawaban yang benar (harus salah satu pilihan yang terisi).',
                ]);
            }

            $hasil['opsi'] = $opsi;
            $hasil['kunci'] = $d['kunci_pg'];
        } elseif ($d['tipe'] === 'isian') {
            if (trim((string) ($d['kunci_isian'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'kunci_isian' => 'Isi jawaban yang benar.',
                ]);
            }

            $hasil['kunci'] = trim($d['kunci_isian']);
        } else {
            $hasil['kunci'] = $d['kunci_esai'] ?? null;
        }

        return $hasil;
    }

    private function simpanGambar(Request $request): string
    {
        $berkas = $request->file('gambar');
        $ext = strtolower($berkas->getClientOriginalExtension());
        $path = $berkas->storeAs('soal', Str::random(24).'.'.$ext, 'local');

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            KompresGambar::proses(Storage::disk('local')->path($path), 1200);
        }

        return $path;
    }

    private function hapusGambar(Soal $soal): void
    {
        if ($soal->gambar) {
            Storage::disk('local')->delete($soal->gambar);
        }
    }

    private function milik(Soal $soal): void
    {
        abort_unless($soal->guru_id === auth()->id(), 403);
    }
}