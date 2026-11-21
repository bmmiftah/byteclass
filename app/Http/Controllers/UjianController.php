<?php

namespace App\Http\Controllers;

use App\Exports\TabelExport;
use App\Models\Kelas;
use App\Models\NilaiDetail;
use App\Models\NilaiSiswa;
use App\Models\Pengerjaan;
use App\Models\Penilaian;
use App\Models\Soal;
use App\Models\Ujian;
use App\Services\KoreksiUjian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class UjianController extends Controller
{
    /* ---------- Daftar dan pengaturan ---------- */

    public function index(Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $ujians = $kelas->ujians()->withCount(['soals', 'pengerjaans'])->get();
        $mapels = auth()->user()->mapels()->orderBy('nama')->get();

        return view('ujian.index', compact('kelas', 'ujians', 'mapels'));
    }

    public function store(Request $request, Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $d = $request->validate([
            'judul' => 'required|string|max:150',
            'durasi' => 'required|integer|min:1|max:300',
            'mapel_id' => 'nullable|integer',
        ]);

        $mapelId = ($d['mapel_id'] ?? null)
            ? auth()->user()->mapels()->whereKey($d['mapel_id'])->value('id')
            : null;

        $ujian = $kelas->ujians()->create([
            'guru_id' => auth()->id(),
            'judul' => $d['judul'],
            'durasi' => $d['durasi'],
            'mapel_id' => $mapelId,
            'token' => Ujian::tokenBaru(),
            'status' => 'draft',
        ]);

        return redirect()->route('ujian.edit', $ujian)->with('ok', 'Ujian dibuat. Sekarang pilih soalnya.');
    }

    public function edit(Ujian $ujian)
    {
        $this->milik($ujian);

        $ujian->load('kelas', 'mapel');
        $terpilih = $ujian->soals()->get()->mapWithKeys(fn ($s) => [$s->id => (int) $s->pivot->bobot]);
        $soals = Soal::where('guru_id', auth()->id())->with('mapel')->orderByDesc('id')->limit(500)->get();
        $mapels = auth()->user()->mapels()->orderBy('nama')->get();
        $terkunci = $ujian->pengerjaans()->exists();

        return view('ujian.edit', compact('ujian', 'terpilih', 'soals', 'mapels', 'terkunci'));
    }

    public function update(Request $request, Ujian $ujian)
    {
        $this->milik($ujian);

        $d = $request->validate([
            'judul' => 'required|string|max:150',
            'durasi' => 'required|integer|min:1|max:300',
            'mapel_id' => 'nullable|integer',
            'buka_pada' => 'nullable|date',
            'tutup_pada' => 'nullable|date',
            'soal_ids' => 'nullable|array',
            'soal_ids.*' => 'integer',
            'bobot' => 'nullable|array',
            'bobot.*' => 'nullable|integer|min:1|max:100',
        ]);

        if (! empty($d['buka_pada']) && ! empty($d['tutup_pada']) && $d['tutup_pada'] < $d['buka_pada']) {
            return back()->withInput()->withErrors([
                'tutup_pada' => 'Waktu tutup harus setelah waktu buka.',
            ]);
        }

        $mapelId = ($d['mapel_id'] ?? null)
            ? auth()->user()->mapels()->whereKey($d['mapel_id'])->value('id')
            : null;

        $ujian->update([
            'judul' => $d['judul'],
            'durasi' => $d['durasi'],
            'mapel_id' => $mapelId,
            'buka_pada' => $d['buka_pada'] ?? null,
            'tutup_pada' => $d['tutup_pada'] ?? null,
            'acak_soal' => $request->boolean('acak_soal'),
            'acak_opsi' => $request->boolean('acak_opsi'),
            'tampil_nilai' => $request->boolean('tampil_nilai'),
        ]);

        // Daftar soal dikunci setelah ada siswa yang mulai
        if (! $ujian->pengerjaans()->exists()) {
            $milik = Soal::where('guru_id', auth()->id())
                ->whereIn('id', $d['soal_ids'] ?? [])
                ->pluck('bobot', 'id');

            $sync = [];
            $n = 0;

            foreach (($d['soal_ids'] ?? []) as $sid) {
                if (! isset($milik[$sid])) {
                    continue;
                }

                $sync[$sid] = [
                    'urutan' => $n++,
                    'bobot' => (int) ($d['bobot'][$sid] ?? $milik[$sid]),
                ];
            }

            $ujian->soals()->sync($sync);
        }

        return back()->with('ok', 'Ujian disimpan.');
    }

    public function destroy(Ujian $ujian)
    {
        $this->milik($ujian);

        if ($ujian->pengerjaans()->exists()) {
            return back()->withErrors([
                'hapus' => 'Ujian yang sudah dikerjakan siswa tidak bisa dihapus.',
            ]);
        }

        $kelasId = $ujian->kelas_id;
        $ujian->delete();

        return redirect()->route('ujian.index', $kelasId)->with('ok', 'Ujian dihapus.');
    }

    public function status(Request $request, Ujian $ujian)
    {
        $this->milik($ujian);

        $status = $request->validate(['status' => 'required|in:draft,buka,tutup'])['status'];

        if ($status === 'buka' && $ujian->soals()->count() === 0) {
            return back()->withErrors([
                'status' => 'Pilih minimal satu soal sebelum membuka ujian.',
            ]);
        }

        if ($status === 'draft' && $ujian->pengerjaans()->exists()) {
            return back()->withErrors([
                'status' => 'Ujian yang sudah dikerjakan tidak bisa dikembalikan ke draft.',
            ]);
        }

        // Menutup ujian = semua yang masih mengerjakan langsung dikumpulkan
        if ($status === 'tutup') {
            $ujian->pengerjaans()
                ->where('status', 'berjalan')
                ->get()
                ->each(fn ($p) => KoreksiUjian::selesaikan($p));
        }

        $ujian->update(['status' => $status]);

        return back()->with('ok', 'Status ujian: '.$ujian->fresh()->labelStatus().'.');
    }

    public function tokenBaru(Ujian $ujian)
    {
        $this->milik($ujian);

        $ujian->update(['token' => Ujian::tokenBaru()]);

        return back()->with('ok', 'Token diganti. Siswa yang sudah mulai tidak terpengaruh.');
    }

    /* ---------- Pemantauan ---------- */

    public function pantau(Ujian $ujian)
    {
        $this->milik($ujian);

        $ujian->load('kelas');

        return view('ujian.pantau', compact('ujian'));
    }

    public function pantauJson(Ujian $ujian)
    {
        $this->milik($ujian);

        KoreksiUjian::rapikanKedaluwarsa($ujian);

        $total = $ujian->soals()->count();
        $siswas = $ujian->kelas->siswas()->where('aktif', true)->orderBy('nama')->get();
        $ps = $ujian->pengerjaans()
            ->withCount(['jawabans as terisi' => fn ($q) => $q->whereNotNull('jawaban')])
            ->get()
            ->keyBy('siswa_id');

        $baris = $siswas->map(function ($s) use ($ps) {
            $p = $ps[$s->id] ?? null;

            return [
                'nama' => $s->nama,
                'pid' => $p?->id,
                'status' => $p ? $p->status : 'belum',
                'terisi' => (int) ($p?->terisi ?? 0),
                'sisa' => $p && $p->status === 'berjalan' ? $p->sisaDetik() : null,
                'nilai' => $p?->nilai,
                'pelanggaran' => (int) ($p?->pelanggaran ?? 0),
                'perlu' => (bool) $p?->perlu_koreksi,
            ];
        })->values();

        return response()->json([
            'total' => $total,
            'status' => $ujian->labelStatus(),
            'baris' => $baris,
        ]);
    }

    // Reset | tambah waktu 5 menit | akhiri paksa
    public function aksi(Request $request, Pengerjaan $pengerjaan)
    {
        $this->milik($pengerjaan->ujian);

        $aksi = $request->validate(['aksi' => 'required|in:reset,tambah,akhiri'])['aksi'];

        if ($aksi === 'reset') {
            $pengerjaan->delete(); // Siswa bisa mulai dari awal; jawaban lama ikut terhapus
        } elseif ($aksi === 'tambah' && $pengerjaan->status === 'berjalan') {
            $pengerjaan->update(['batas_pada' => $pengerjaan->batas_pada->copy()->addMinutes(5)]);
        } elseif ($aksi === 'akhiri') {
            KoreksiUjian::selesaikan($pengerjaan);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('ok', 'Selesai.');
    }

    /* ---------- Hasil dan koreksi ---------- */

    public function hasil(Request $request, Ujian $ujian)
    {
        $this->milik($ujian);

        KoreksiUjian::rapikanKedaluwarsa($ujian);

        $total = $ujian->soals()->count();
        $siswas = $ujian->kelas->siswas()->where('aktif', true)->orderBy('nama')->get();
        $ps = $ujian->pengerjaans()
            ->withCount(['jawabans as benar_count' => fn ($q) => $q->where('benar', true)])
            ->get()
            ->keyBy('siswa_id');

        $baris = $siswas->map(function ($s) use ($ps) {
            $p = $ps[$s->id] ?? null;

            return [
                'nama' => $s->nama,
                'p' => $p,
                'status' => $p ? ($p->status === 'selesai' ? 'Selesai' : 'Mengerjakan') : 'Belum',
            ];
        });

        if ($request->boolean('unduh')) {
            $isi = $baris->values()->map(fn ($b, $i) => [
                $i + 1,
                $b['nama'],
                $b['status'],
                $b['p']?->nilai,
                $b['p']?->benar_count,
                $b['p']?->pelanggaran,
            ])->all();

            return Excel::download(
                new TabelExport(['No', 'Nama', 'Status', 'Nilai', 'Jumlah benar (otomatis)', 'Pelanggaran'], $isi),
                'ujian-'.Str::slug($ujian->judul).'.xlsx'
            );
        }

        $selesai = $ps->where('status', 'selesai');

        return view('ujian.hasil', [
            'ujian' => $ujian,
            'total' => $total,
            'baris' => $baris,
            'rata' => $selesai->count() ? round($selesai->avg('nilai'), 1) : null,
            'tinggi' => $selesai->max('nilai'),
            'rendah' => $selesai->min('nilai'),
            'perlu' => $ps->where('perlu_koreksi', true)->count(),
            'berjalan' => $ps->where('status', 'berjalan')->count(),
        ]);
    }

    public function koreksi(Ujian $ujian, Pengerjaan $pengerjaan)
    {
        $this->milikPengerjaan($ujian, $pengerjaan);

        $soals = $ujian->soals()->get();
        $jawabans = $pengerjaan->jawabans()->get()->keyBy('soal_id');
        $pengerjaan->load('siswa');

        return view('ujian.koreksi', compact('ujian', 'pengerjaan', 'soals', 'jawabans'));
    }

    public function simpanKoreksi(Request $request, Ujian $ujian, Pengerjaan $pengerjaan)
    {
        $this->milikPengerjaan($ujian, $pengerjaan);

        $esai = $ujian->soals()->get()->where('tipe', 'esai');
        $aturan = [];

        foreach ($esai as $s) {
            $aturan['skor.'.$s->id] = 'nullable|numeric|min:0|max:'.(int) $s->pivot->bobot;
        }

        $data = $request->validate($aturan);

        foreach ($esai as $s) {
            $nilai = $data['skor'][$s->id] ?? null;
            if ($nilai === null) {
                continue;
            }

            $pengerjaan->jawabans()->updateOrCreate(['soal_id' => $s->id], ['skor' => $nilai]);
        }

        KoreksiUjian::hitung($pengerjaan);

        return redirect()->route('ujian.hasil', $ujian)->with('ok', 'Koreksi disimpan.');
    }

    // Salin nilai ujian ke modul Penilaian (satu kriteria: "Nilai CBT")
    public function kirimNilai(Ujian $ujian)
    {
        $this->milik($ujian);

        KoreksiUjian::rapikanKedaluwarsa($ujian);

        if ($ujian->pengerjaans()->where('status', 'berjalan')->exists()) {
            return back()->withErrors([
                'kirim' => 'Masih ada siswa yang mengerjakan. Tutup ujian atau tunggu selesai.',
            ]);
        }

        if ($ujian->pengerjaans()->where('perlu_koreksi', true)->exists()) {
            return back()->withErrors([
                'kirim' => 'Masih ada uraian yang belum dikoreksi.',
            ]);
        }

        $penilaian = DB::transaction(function () use ($ujian) {
            $penilaian = $ujian->penilaian ?? Penilaian::create([
                'kelas_id' => $ujian->kelas_id,
                'mapel_id' => $ujian->mapel_id,
                'judul' => 'CBT: '.$ujian->judul,
                'jenis' => 'individu',
                'tanggal' => today(),
                'kkm' => 75,
            ]);

            $kriteria = $penilaian->kriterias()->first() ?? $penilaian->kriterias()->create([
                'nama' => 'Nilai CBT',
                'tipe' => 'angka',
                'maks' => 100,
                'bobot' => 1,
                'urutan' => 0,
            ]);

            foreach ($ujian->pengerjaans()->where('status', 'selesai')->get() as $p) {
                $ns = NilaiSiswa::updateOrCreate(
                    ['penilaian_id' => $penilaian->id, 'siswa_id' => $p->siswa_id],
                    ['total' => $p->nilai]
                );

                NilaiDetail::updateOrCreate(
                    ['nilai_siswa_id' => $ns->id, 'kriteria_id' => $kriteria->id],
                    ['nilai' => $p->nilai]
                );
            }

            $ujian->update(['penilaian_id' => $penilaian->id]);

            return $penilaian;
        });

        return redirect()->route('penilaian.show', $penilaian)->with('ok', 'Nilai ujian masuk ke Penilaian.');
    }

    /* ---------- Bantu ---------- */

    private function milik(Ujian $ujian): void
    {
        abort_unless($ujian->guru_id === auth()->id(), 403);
    }

    private function milikKelas(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }

    private function milikPengerjaan(Ujian $ujian, Pengerjaan $pengerjaan): void
    {
        $this->milik($ujian);
        abort_unless($pengerjaan->ujian_id === $ujian->id && $pengerjaan->status === 'selesai', 404);
    }
}