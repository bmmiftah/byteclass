<?php

namespace App\Http\Controllers;

use App\Exports\TabelExport;
use App\Models\Kelas;
use App\Models\NilaiSiswa;
use App\Models\Penilaian;
use App\Services\HitungNilai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PenilaianController extends Controller
{
    /* ---------- Daftar & buat ---------- */

    public function index(Kelas $kelas)
    {
        $this->milikKelas($kelas);
        $babs = $kelas->materiTampil()->get();

        $user = auth()->user();
        $penilaians = $kelas->penilaians()
            ->with('mapel')
            ->withCount('nilais')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();
        $mapels = $user->mapels()->orderBy('nama')->get();
        $rubriks = $user->rubriks()->orderBy('nama')->get();
        $sesis = $kelas->kelompokSesis()->latest()->get();
        $jumlahSiswa = $kelas->siswas()->where('aktif', true)->count();

        return view('penilaian.index', compact('kelas', 'penilaians', 'mapels', 'rubriks', 'sesis', 'jumlahSiswa', 'babs'));
    }

    public function store(Request $request, Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $user = auth()->user();
        $data = $request->validate([
            'judul' => 'required|string|max:100',
            'mapel_id' => 'nullable|integer',
            'materi' => 'nullable|string|max:150',
            'jenis' => 'required|in:individu,kelompok',
            'kelompok_sesi_id' => 'nullable|required_if:jenis,kelompok|integer',
            'rubrik_id' => 'required|integer',
            'tanggal' => 'required|date',
            'kkm' => 'required|integer|min:0|max:100',
            'materi_bab_id'	=>	'nullable|integer',
        ]);

        $rubrik = $user->rubriks()->with('kriterias')->findOrFail($data['rubrik_id']);
        $mapel = $data['mapel_id'] ? $user->mapels()->findOrFail($data['mapel_id']) : null;
        $sesi = $data['jenis'] === 'kelompok' ? $kelas->kelompokSesis()->findOrFail($data['kelompok_sesi_id']) : null;
        $bab = $data['materi_bab_id'] ? $kelas->materiTampil()->findOrFail($data['materi_bab_id']) : null;

        $penilaian = DB::transaction(function () use ($kelas, $data, $rubrik, $mapel, $sesi, $bab) {
            $penilaian = $kelas->penilaians()->create([
                'mapel_id' => $mapel?->id,
                'rubrik_id' => $rubrik->id,
                'kelompok_sesi_id' => $sesi?->id,
                'judul' => $data['judul'],
                'materi_bab_id' => $bab?->id,
                'materi' => ($data['materi'] ?? null) ?: $bab?->judul,
                'jenis' => $data['jenis'],
                'tanggal' => $data['tanggal'],
                'kkm' => $data['kkm'],
            ]);

            // salin kriteria rubrik -> penilaian (snapshot)
            foreach ($rubrik->kriterias as $k) {
                $penilaian->kriterias()->create($k->only(['nama', 'tipe', 'maks', 'bobot', 'urutan']));
            }

            return $penilaian;
        });

        return redirect()->route('penilaian.nilai', $penilaian);
    }

    public function destroy(Penilaian $penilaian)
    {
        $this->milik($penilaian);

        $kelasId = $penilaian->kelas_id;
        $penilaian->delete();

        return redirect()->route('penilaian.index', $kelasId)->with('ok', 'Penilaian dihapus.');
    }

    /* ---------- Rekap satu penilaian ---------- */

    public function show(Penilaian $penilaian)
    {
        $this->milik($penilaian);

        return view('penilaian.show', array_merge(['penilaian' => $penilaian], $this->rekap($penilaian)));
    }

    public function ekspor(Penilaian $penilaian)
    {
        $this->milik($penilaian);

        $r = $this->rekap($penilaian);
        $label = ['belum' => 'Belum dinilai', 'sebagian' => 'Sebagian', 'lengkap' => 'Lengkap'];
        $judul = array_merge(
            ['No', 'NIS', 'Nama'],
            $r['kriterias']->pluck('nama')->all(),
            ['Total', 'Status', 'Ketuntasan', 'Catatan']
        );
        $baris = [];

        foreach ($r['baris'] as $no => $b) {
            $baris[] = array_merge(
                [$no + 1, $b['siswa']->nis, $b['siswa']->nama],
                $r['kriterias']->map(fn ($k) => $b['detail'][$k->id] ?? '')->all(),
                [
                    $b['total'] ?? '',
                    $label[$b['status']],
                    $b['total'] === null ? '' : ($b['tuntas'] ? 'Tuntas' : 'Belum tuntas'),
                    $b['catatan'] ?? '',
                ]
            );
        }

        return Excel::download(new TabelExport($judul, $baris), 'nilai-'.Str::slug($penilaian->judul).'.xlsx');
    }

    /* ---------- Mode nilai cepat ---------- */

    public function nilai(Penilaian $penilaian)
    {
        $this->milik($penilaian);

        $siswas = $penilaian->kelas->siswas()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'tampil' => $s->nama_panggilan ?: Str::words($s->nama, 1, ''),
            ])
            ->values();

        $kelompoks = [];
        if ($penilaian->jenis === 'kelompok' && $penilaian->kelompokSesi) {
            $kelompoks = $penilaian->kelompokSesi->kelompoks()
                ->with('anggotas')
                ->orderBy('urutan')
                ->get()
                ->map(fn ($k) => [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'anggota' => $k->anggotas->pluck('siswa_id')->values(),
                ])
                ->values();
        }

        $nilai = $penilaian->nilais()->with('details')->get()->mapWithKeys(fn ($n) => [
            $n->siswa_id => [
                'total' => $n->total !== null ? (float) $n->total : null,
                'catatan' => $n->catatan,
                'detail' => $n->details->mapWithKeys(fn ($d) => [$d->kriteria_id => (float) $d->nilai])->all(),
            ],
        ])->all();

        $data = [
            'penilaian' => [
                'id' => $penilaian->id,
                'judul' => $penilaian->judul,
                'jenis' => $penilaian->jenis,
                'kkm' => $penilaian->kkm,
            ],
            'kriteria' => $penilaian->kriterias->map(fn ($k) => $k->only(['id', 'nama', 'tipe', 'maks', 'bobot']))->values(),
            'siswas' => $siswas,
            'kelompoks' => $kelompoks,
            'nilai' => (object) $nilai,
            'urls' => ['simpan' => route('penilaian.simpan', $penilaian)],
        ];

        return view('penilaian.nilai', compact('penilaian', 'data'));
    }

    // Simpan nilai satu unit (satu siswa, atau semua anggota satu kelompok)
    public function simpan(Request $request, Penilaian $penilaian)
    {
        $this->milik($penilaian);

        $v = $request->validate([
            'siswa_ids' => 'required|array|min:1|max:60',
            'siswa_ids.*' => 'integer',
            'detail' => 'present|array',
            'catatan' => 'nullable|string|max:500',
        ]);

        $kriterias = $penilaian->kriterias->keyBy('id');
        $validSiswa = $penilaian->kelas->siswas()->pluck('id')->all();
        $ids = array_values(array_intersect($v['siswa_ids'], $validSiswa));

        if (! $ids) {
            return response()->json(['pesan' => 'Siswa tidak valid.'], 422);
        }

        // bersihkan nilai: hanya kriteria milik penilaian ini, dibatasi 0..maks
        $bersih = [];
        foreach ($v['detail'] as $idKriteria => $nilai) {
            $k = $kriterias->get((int) $idKriteria);
            if (! $k || $nilai === null || $nilai === '') {
                continue;
            }

            $x = max(0, min((float) $nilai, (float) $k->maks));
            if ($k->tipe === 'ceklis') {
                $x = $x > 0 ? 1 : 0;
            }

            $bersih[$k->id] = $x;
        }

        $total = HitungNilai::total($kriterias, $bersih);
        $lengkap = count($bersih) === $kriterias->count();

        DB::transaction(function () use ($penilaian, $ids, $bersih, $total, $v) {
            foreach ($ids as $sid) {
                $n = NilaiSiswa::updateOrCreate(
                    ['penilaian_id' => $penilaian->id, 'siswa_id' => $sid],
                    ['total' => $total, 'catatan' => $v['catatan'] ?? null]
                );

                $n->details()->delete();
                foreach ($bersih as $idKriteria => $x) {
                    $n->details()->create(['kriteria_id' => $idKriteria, 'nilai' => $x]);
                }
            }
        });

        return response()->json(['total' => $total, 'lengkap' => $lengkap]);
    }

    /* ---------- Rekap seluruh kelas ---------- */

    public function rekapKelas(Kelas $kelas)
    {
        $this->milikKelas($kelas);

        return view('penilaian.rekap-kelas', array_merge(['kelas' => $kelas], $this->matriks($kelas)));
    }

    public function eksporKelas(Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $m = $this->matriks($kelas);
        $judul = array_merge(['No', 'NIS', 'Nama'], $m['penilaians']->pluck('judul')->all(), ['Rata-rata']);
        $baris = [];

        foreach ($m['baris'] as $no => $b) {
            $baris[] = array_merge(
                [$no + 1, $b['siswa']->nis, $b['siswa']->nama],
                $m['penilaians']->map(fn ($p) => $b['nilai'][$p->id] ?? '')->all(),
                [$b['rata'] ?? '']
            );
        }

        return Excel::download(new TabelExport($judul, $baris), 'rekap-nilai-'.Str::slug($kelas->nama).'.xlsx');
    }

    /* ---------- Bantu ---------- */

    private function rekap(Penilaian $p): array
    {
        $kriterias = $p->kriterias;
        $siswas = $p->kelas->siswas()->where('aktif', true)->orderBy('nama')->get();
        $nilais = $p->nilais()->with('details')->get()->keyBy('siswa_id');
        $baris = [];
        $totals = [];

        foreach ($siswas as $s) {
            $n = $nilais->get($s->id);
            $detail = $n ? $n->details->mapWithKeys(fn ($d) => [$d->kriteria_id => (float) $d->nilai])->all() : [];
            $total = $n && $n->total !== null ? (float) $n->total : null;

            if ($n) {
                $totals[] = $total ?? 0;
            }

            $baris[] = [
                'siswa' => $s,
                'detail' => $detail,
                'total' => $total,
                'status' => ! $n ? 'belum' : (count($detail) >= $kriterias->count() ? 'lengkap' : 'sebagian'),
                'catatan' => $n?->catatan,
                'tuntas' => $total !== null && $total >= $p->kkm,
            ];
        }

        $dinilai = count($totals);
        $stat = [
            'jumlah' => $siswas->count(),
            'dinilai' => $dinilai,
            'rata' => $dinilai ? round(array_sum($totals) / $dinilai, 1) : null,
            'tinggi' => $dinilai ? max($totals) : null,
            'rendah' => $dinilai ? min($totals) : null,
            'tuntas' => count(array_filter($totals, fn ($t) => $t >= $p->kkm)),
        ];

        $rentang = ['<60' => 0, '60-69' => 0, '70-79' => 0, '80-89' => 0, '90-100' => 0];
        foreach ($totals as $t) {
            $kunci = $t < 60 ? '<60' : ($t < 70 ? '60-69' : ($t < 80 ? '70-79' : ($t < 90 ? '80-89' : '90-100')));
            $rentang[$kunci]++;
        }

        return ['kriterias' => $kriterias, 'baris' => $baris, 'stat' => $stat, 'distribusi' => $rentang];
    }

    private function matriks(Kelas $kelas): array
    {
        $penilaians = $kelas->penilaians()->orderBy('tanggal')->orderBy('id')->get();
        $siswas = $kelas->siswas()->where('aktif', true)->orderBy('nama')->get();
        $nilai = NilaiSiswa::whereIn('penilaian_id', $penilaians->pluck('id'))->get()->groupBy('siswa_id');
        $baris = [];

        foreach ($siswas as $s) {
            $per = [];
            $vals = [];

            foreach ($penilaians as $p) {
                $n = $nilai->get($s->id)?->firstWhere('penilaian_id', $p->id);
                $t = $n && $n->total !== null ? (float) $n->total : null;
                $per[$p->id] = $t;

                if ($t !== null) {
                    $vals[] = $t;
                }
            }

            $baris[] = [
                'siswa' => $s,
                'nilai' => $per,
                'rata' => $vals ? round(array_sum($vals) / count($vals), 1) : null,
            ];
        }

        return ['penilaians' => $penilaians, 'baris' => $baris];
    }

    private function milik(Penilaian $penilaian): void
    {
        abort_unless($penilaian->kelas->guru_id === auth()->id(), 403);
    }

    private function milikKelas(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}