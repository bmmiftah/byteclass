<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\LegerPengaturan;
use App\Models\NilaiSiswa;
use App\Models\Penilaian;
use App\Models\Tugas;
use App\Models\Ujian;
use Illuminate\Support\Collection;

/**
 * Leger nilai akhir: menggabungkan semua Penilaian satu kelas (dan satu mapel) menjadi satu nilai rapor per siswa.
 *
 * Alur: nilai tiap penilaian (0-100) -> rata-rata per KATEGORI -> rata-rata berbobot antar kategori -> nilai rapor.
 * Sumber nilainya tetap satu: tabel Penilaian. Tugas dan ujian ikut masuk lewat tombol "kirim nilai" yang sudah ada.
 */
class LegerNilai
{
    public const KATEGORI = [
        'tugas'   => 'Tugas',
        'harian'  => 'Ulangan Harian',
        'praktik' => 'Praktik / Proyek',
        'pts'     => 'Tengah Semester',
        'pas'     => 'Akhir Semester',
    ];

    public const BOBOT_AWAL = [
        'tugas'   => 20,
        'harian'  => 20,
        'praktik' => 20,
        'pts'     => 15,
        'pas'     => 25,
    ];

    public const KKM_AWAL = 75;

    /** Pengaturan tersimpan (atau nilai awal) untuk satu kelas dan mapel. */
    public static function pengaturan(Kelas $kelas, int $mapelId): array
    {
        $p = LegerPengaturan::where('kelas_id', $kelas->id)->where('mapel_id', $mapelId)->first();
        $bobot = self::BOBOT_AWAL;

        foreach ((array) ($p?->bobot ?? []) as $k => $v) {
            if (array_key_exists($k, $bobot)) {
                $bobot[$k] = max(0, min(100, (int) $v));
            }
        }

        return [
            'kkm'    => (int) ($p?->kkm ?? self::KKM_AWAL),
            'kosong' => in_array($p?->kosong, ['lewati', 'nol'], true) ? $p->kosong : 'lewati',
            'bobot'  => $bobot,
        ];
    }

    /** Penilaian satu kelas+mapel, terurut. */
    public static function penilaians(Kelas $kelas, int $mapelId): Collection
    {
        return Penilaian::where('kelas_id', $kelas->id)
            ->when(
                $mapelId > 0,
                fn ($q) => $q->where('mapel_id', $mapelId),
                fn ($q) => $q->whereNull('mapel_id')
            )
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    /** Kategori tiap penilaian: pilihan guru kalau ada, kalau tidak ditebak dari asalnya. Hasil: [penilaian_id => kategori]. */
    public static function kategoriMap(Kelas $kelas, Collection $penilaians): array
    {
        $dariTugas = Tugas::where('kelas_id', $kelas->id)->whereNotNull('penilaian_id')->pluck('penilaian_id')->all();
        $dariUjian = Ujian::where('kelas_id', $kelas->id)->whereNotNull('penilaian_id')->pluck('penilaian_id')->all();
        $peta = [];

        foreach ($penilaians as $p) {
            $peta[$p->id] = isset(self::KATEGORI[$p->kategori])
                ? $p->kategori
                : (in_array($p->id, $dariTugas, true) ? 'tugas' : (in_array($p->id, $dariUjian, true) ? 'harian' : 'praktik'));
        }

        return $peta;
    }

    /** Asal tiap penilaian untuk ditampilkan: [penilaian_id => 'Tugas'|'Ujian'|'Penilaian']. */
    public static function asalMap(Kelas $kelas, Collection $penilaians): array
    {
        $dariTugas = Tugas::where('kelas_id', $kelas->id)->whereNotNull('penilaian_id')->pluck('penilaian_id')->all();
        $dariUjian = Ujian::where('kelas_id', $kelas->id)->whereNotNull('penilaian_id')->pluck('penilaian_id')->all();

        return $penilaians->mapWithKeys(fn ($p) => [
            $p->id => in_array($p->id, $dariTugas, true) ? 'Tugas' : (in_array($p->id, $dariUjian, true) ? 'Ujian' : 'Penilaian'),
        ])->all();
    }

    /**
     * Predikat memakai interval standar dari KKM: D di bawah KKM, lalu sisanya (KKM sampai 100) dibagi tiga: C, B, A.
     */
    public static function predikat(?int $nilai, int $kkm): string
    {
        if ($nilai === null) {
            return '-';
        }

        $interval = (100 - $kkm) / 3;

        return match (true) {
            $nilai < $kkm                  => 'D',
            $nilai < $kkm + $interval      => 'C',
            $nilai < $kkm + 2 * $interval  => 'B',
            default                        => 'A',
        };
    }

    /**
     * @return array{
     *     penilaians: Collection,
     *     kategoriMap: array,
     *     kategoriAktif: array,
     *     pengaturan: array,
     *     baris: array,
     *     statistik: array,
     *     remedial: array
     * }
     */
    public static function hitung(Kelas $kelas, int $mapelId): array
    {
        $set = self::pengaturan($kelas, $mapelId);
        $penilaians = self::penilaians($kelas, $mapelId);
        $peta = self::kategoriMap($kelas, $penilaians);
        $siswas = $kelas->siswas()->where('aktif', true)->orderBy('nama')->get();
        $nilai = NilaiSiswa::whereIn('penilaian_id', $penilaians->pluck('id'))->get()->groupBy('siswa_id');

        // Kategori yang dipakai = punya penilaian dan berbobot > 0 (urutan mengikuti KATEGORI)
        $aktif = array_values(array_filter(
            array_keys(self::KATEGORI),
            fn ($k) => in_array($k, $peta, true) && ($set['bobot'][$k] ?? 0) > 0
        ));

        $perKategori = [];
        foreach ($penilaians as $p) {
            $perKategori[$peta[$p->id]][] = $p->id;
        }

        $baris = [];
        foreach ($siswas as $s) {
            $punya = ($nilai->get($s->id) ?? collect())
                ->filter(fn ($n) => $n->total !== null)
                ->mapWithKeys(fn ($n) => [$n->penilaian_id => (float) $n->total]);

            $rata = [];
            $kosong = 0;

            foreach ($aktif as $k) {
                $ids = $perKategori[$k];
                $isi = array_values(array_filter(array_map(fn ($id) => $punya->get($id), $ids), fn ($v) => $v !== null));
                $kosong += count($ids) - count($isi);

                if ($set['kosong'] === 'nol') {
                    $rata[$k] = round(array_sum($isi) / count($ids), 2);
                } else {
                    $rata[$k] = $isi ? round(array_sum($isi) / count($isi), 2) : null;
                }
            }

            $atas = 0.0;
            $bawah = 0;

            foreach ($rata as $k => $v) {
                if ($v !== null) {
                    $atas += $v * $set['bobot'][$k];
                    $bawah += $set['bobot'][$k];
                }
            }

            $akhir = $bawah > 0 ? round($atas / $bawah, 1) : null;
            $rapor = $akhir === null ? null : (int) round($atas / $bawah); // dibulatkan ke bilangan bulat, .5 ke atas naik

            $baris[] = [
                'siswa'      => $s,
                'rata'       => $rata,
                'akhir'      => $akhir,
                'rapor'      => $rapor,
                'predikat'   => self::predikat($rapor, $set['kkm']),
                'tuntas'     => $rapor !== null && $rapor >= $set['kkm'],
                'kosong'     => $kosong,
                'sementara' => $kosong > 0,
            ];
        }

        $rapor = array_values(array_filter(array_column($baris, 'rapor'), fn ($v) => $v !== null));
        $statistik = [
            'siswa'    => count($baris),
            'ada'      => count($rapor),
            'rata'     => $rapor ? round(array_sum($rapor) / count($rapor), 1) : null,
            'tinggi'   => $rapor ? max($rapor) : null,
            'rendah'   => $rapor ? min($rapor) : null,
            'tuntas'   => count(array_filter($rapor, fn ($v) => $v >= $set['kkm'])),
            'remedial' => count(array_filter($rapor, fn ($v) => $v < $set['kkm'])),
            'sementara' => count(array_filter($baris, fn ($b) => $b['sementara'])),
        ];

        $remedial = array_values(array_filter($baris, fn ($b) => $b['rapor'] !== null && ! $b['tuntas']));
        usort($remedial, fn ($a, $b) => $a['rapor'] <=> $b['rapor']);

        return [
            'penilaians'    => $penilaians,
            'kategoriMap'   => $peta,
            'kategoriAktif' => $aktif,
            'pengaturan'    => $set,
            'baris'         => $baris,
            'statistik'     => $statistik,
            'remedial'      => $remedial,
        ];
    }

    /** Tugas dan ujian yang sudah bernilai tapi belum dikirim ke Penilaian (jadi belum masuk leger). */
    public static function belumMasuk(Kelas $kelas, int $mapelId): array
    {
        $mapel = fn ($q) => $q->when(
            $mapelId > 0,
            fn ($w) => $w->where('mapel_id', $mapelId),
            fn ($w) => $w->whereNull('mapel_id')
        );

        $tugas = $mapel(Tugas::where('kelas_id', $kelas->id)->where('status', '!=', 'draf')->whereNull('penilaian_id'))
            ->whereHas('pengumpulans', fn ($q) => $q->whereNotNull('nilai'))
            ->orderBy('tenggat')
            ->get(['id', 'judul']);

        $ujian = $mapel(Ujian::where('kelas_id', $kelas->id)->whereNull('penilaian_id'))
            ->whereHas('pengerjaans', fn ($q) => $q->where('status', 'selesai')->whereNotNull('nilai'))
            ->orderBy('id')
            ->get(['id', 'judul']);

        return ['tugas' => $tugas, 'ujian' => $ujian];
    }

    /**
     * Tabel untuk ekspor Excel (memakai TabelExport yang sama dengan rekap Fase 3).
     *
     * @return array{0: array<int,string>, 1: array<int,array>}
     */
    public static function tabelExport(Kelas $kelas, array $h): array
    {
        $judul = array_merge(
            ['No', 'NIS', 'Nama'],
            array_map(fn ($k) => self::KATEGORI[$k], $h['kategoriAktif']),
            ['Nilai Akhir', 'Nilai Rapor', 'Predikat', 'Keterangan']
        );

        $baris = [];
        foreach ($h['baris'] as $i => $b) {
            $ket = $b['rapor'] === null ? 'Belum ada nilai' : ($b['tuntas'] ? 'Tuntas' : 'Remedial');
            if ($b['sementara'] && $b['rapor'] !== null) {
                $ket .= ' (sementara)';
            }

            $baris[] = array_merge(
                [$i + 1, $b['siswa']->nis, $b['siswa']->nama],
                array_map(fn ($k) => $b['rata'][$k] ?? '', $h['kategoriAktif']),
                [$b['akhir'] ?? '', $b['rapor'] ?? '', $b['predikat'], $ket]
            );
        }

        return [$judul, $baris];
    }
}