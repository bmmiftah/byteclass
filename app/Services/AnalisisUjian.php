<?php

namespace App\Services;

use App\Models\Soal;
use App\Models\Ujian;
use Illuminate\Support\Str;

/**
 * Analisis butir soal dari hasil ujian CBT yang sudah selesai.
 *
 * Per soal: tingkat kesukaran (rata-rata skor / bobot), daya pembeda (kelompok atas 27% dikurangi bawah 27%),
 * sebaran pilihan jawaban, dan catatan otomatis. Per ujian: ringkasan, sebaran nilai, dan kandidat remedial.
 * Pengerjaan yang masih menunggu koreksi uraian TIDAK ikut dianalisis supaya angkanya tidak miring.
 */
class AnalisisUjian
{
    public const MIN_DAYA_PEMBEDA = 10; // daya pembeda baru bermakna dari 10 peserta
    public const SALAH_JIKA_DI_BAWAH = 0.6; // skor di bawah 60% bobot dihitung salah (uraian)

    public static function kesukaran(float $p): string
    {
        return $p < 0.30 ? 'Sulit' : ($p > 0.70 ? 'Mudah' : 'Sedang');
    }

    public static function labelDayaPembeda(?float $d): string
    {
        return match (true) {
            $d === null => '-',
            $d < 0      => 'Negatif',
            $d < 0.20   => 'Jelek',
            $d < 0.30   => 'Cukup',
            $d < 0.40   => 'Baik',
            default     => 'Sangat baik',
        };
    }

    public static function hitung(Ujian $ujian, int $kkm): array
    {
        $soals = $ujian->soals()->get();
        $semua = $ujian->pengerjaans()->where('status', 'selesai')->with('jawabans')->get();
        $ps = $semua->where('perlu_koreksi', false)->values();
        $n = $ps->count();
        $nilai = $ps->pluck('nilai')->map(fn ($v) => (float) $v)->sort()->values();

        $ringkas = [
            'peserta'  => $n,
            'menunggu' => $semua->count() - $n,
            'rata'     => $n ? round($nilai->avg(), 1) : null,
            'median'   => $n ? round(self::median($nilai->all()), 1) : null,
            'tinggi'   => $n ? $nilai->max() : null,
            'rendah'   => $n ? $nilai->min() : null,
            'sd'       => $n ? round(sqrt($nilai->map(fn ($v) => ($v - $nilai->avg()) ** 2)->avg()), 1) : null,
            'tuntas'   => $nilai->filter(fn ($v) => $v >= $kkm)->count(),
            'remedial' => $nilai->filter(fn ($v) => $v < $kkm)->count(),
            'belum'    => $ujian->siswaPeserta()->whereNotIn('id', $ujian->pengerjaans()->select('siswa_id'))->count(),
        ];

        $sebaran = array_fill(0, 10, 0);
        foreach ($nilai as $v) {
            $sebaran[min(9, (int) floor($v / 10))]++;
        }

        // Kelompok atas dan bawah 27%
        $urut = $ps->sortByDesc(fn ($p) => (float) $p->nilai)->values();
        $k = max(1, (int) round(0.27 * $n));
        $atas = $urut->take($k);
        $bawah = $urut->slice($n - $k);
        $bisaDP = $n >= self::MIN_DAYA_PEMBEDA;

        $hasilSoal = [];
        foreach ($soals as $i => $soal) {
            $hasilSoal[] = self::soal($soal, $i + 1, (int) $soal->pivot->bobot, $ps, $atas, $bawah, $bisaDP);
        }

        // Kandidat remedial (hanya yang nilainya sudah final)
        $kandidat = $ps->filter(fn ($p) => (float) $p->nilai < $kkm)
            ->sortBy(fn ($p) => (float) $p->nilai)
            ->values();

        $salahPerSoal = [];
        foreach ($soals as $soal) {
            $bobot = (int) $soal->pivot->bobot;
            $salahPerSoal[$soal->id] = $kandidat->filter(
                fn ($p) => self::frac($p, $soal->id, $bobot) < self::SALAH_JIKA_DI_BAWAH
            )->count();
        }

        return [
            'kkm'           => $kkm,
            'ringkas'       => $ringkas,
            'sebaran'       => $sebaran,
            'soal'          => $hasilSoal,
            'kandidat'      => $kandidat,
            'salahKandidat' => $salahPerSoal,
            'rekomendasi'   => self::rekomendasiSoalRemedial($salahPerSoal, $kandidat->count()),
        ];
    }

    /** Soal untuk paket remedial: yang salah pada separuh kandidat atau lebih; kalau kurang dari 3, ambil 5 teratas. */
    public static function rekomendasiSoalRemedial(array $salahPerSoal, int $jumlahKandidat): array
    {
        if ($jumlahKandidat === 0) {
            return [];
        }

        arsort($salahPerSoal);
        $pilih = array_keys(array_filter($salahPerSoal, fn ($c) => $c / $jumlahKandidat >= 0.5));

        if (count($pilih) < 3) {
            $pilih = array_keys(array_slice(array_filter($salahPerSoal, fn ($c) => $c > 0), 0, 5, true));
        }

        return array_map('intval', $pilih);
    }

    /** Skor siswa pada soal sebagai pecahan 0..1 dari bobot. Baris jawaban yang hilang dianggap 0. */
    public static function frac($pengerjaan, int $soalId, int $bobot): float
    {
        $j = $pengerjaan->jawabans->firstWhere('soal_id', $soalId);

        return $bobot > 0 ? max(0.0, min(1.0, (float) ($j?->skor ?? 0) / $bobot)) : 0.0;
    }

    private static function soal(Soal $soal, int $no, int $bobot, $ps, $atas, $bawah, bool $bisaDP): array
    {
        $n = $ps->count();
        $p = $n ? $ps->avg(fn ($x) => self::frac($x, $soal->id, $bobot)) : 0.0;
        $dp = null;

        if ($bisaDP) {
            $dp = round(
                $atas->avg(fn ($x) => self::frac($x, $soal->id, $bobot))
                    - $bawah->avg(fn ($x) => self::frac($x, $soal->id, $bobot)),
                2
            );
        }

        $catatan = [];
        $perhatian = false;
        $opsi = null;
        $kosong = 0;
        $salahTerbanyak = [];

        if ($soal->tipe === 'pg') {
            $kunci = strtoupper(trim((string) $soal->kunci));
            $hitung = [];

            foreach (array_keys((array) $soal->opsi) as $huruf) {
                $hitung[strtoupper((string) $huruf)] = 0;
            }

            foreach ($ps as $x) {
                $j = strtoupper(trim((string) $x->jawabans->firstWhere('soal_id', $soal->id)?->jawaban));
                if ($j === '' || ! array_key_exists($j, $hitung)) {
                    $kosong++;
                } else {
                    $hitung[$j]++;
                }
            }

            $opsi = [];
            foreach ((array) $soal->opsi as $huruf => $teks) {
                $h = strtoupper((string) $huruf);
                $opsi[] = [
                    'huruf'  => $h,
                    'teks'   => Str::limit(strip_tags(is_array($teks) ? (string) ($teks['teks'] ?? '') : (string) $teks), 60),
                    'jumlah' => $hitung[$h],
                    'persen' => $n ? (int) round($hitung[$h] / $n * 100) : 0,
                    'kunci'  => $h === $kunci,
                ];
            }

            $salah = array_filter($hitung, fn ($c, $h) => $h !== $kunci, ARRAY_FILTER_USE_BOTH);
            if ($salah && $n) {
                $top = array_keys($salah, max($salah))[0];
                if ($salah[$top] > ($hitung[$kunci] ?? 0) && $salah[$top] / $n >= 0.4) {
                    $catatan[] = "Cek kunci: pilihan {$top} lebih banyak dipilih ({$salah[$top]} siswa) daripada kunci {$kunci} (" . ($hitung[$kunci] ?? 0) . ' siswa).';
                    $perhatian = true;
                }
            }

            if ($bisaDP) {
                $mati = array_keys(array_filter($salah, fn ($c) => $c === 0));
                if ($mati) {
                    $catatan[] = 'Pengecoh ' . implode(', ', $mati) . ' tidak dipilih siapa pun.';
                }
            }
        } elseif ($soal->tipe === 'isian') {
            $hitung = [];
            foreach ($ps as $x) {
                $j = $x->jawabans->firstWhere('soal_id', $soal->id);
                $teks = KoreksiUjian::normal($j?->jawaban);
                if ($teks === '') {
                    $kosong++;
                } elseif (! KoreksiUjian::cocok($soal, $j->jawaban)) {
                    $hitung[$teks] = ($hitung[$teks] ?? 0) + 1;
                }
            }

            arsort($hitung);
            foreach (array_slice($hitung, 0, 3, true) as $teks => $c) {
                if ($c >= max(2, (int) ceil(0.1 * $n))) {
                    $salahTerbanyak[] = ['teks' => Str::limit($teks, 40), 'jumlah' => $c];
                    $catatan[] = 'Jawaban "' . Str::limit($teks, 40) . "\" ditulis {$c} siswa dan dianggap salah. Kalau sebenarnya benar, salin soalnya dan tambahkan sebagai kunci alternatif di versi baru.";
                }
            }
        }

        if ($dp !== null && $dp < 0) {
            $catatan[] = 'Daya pembeda negatif: siswa yang nilainya tinggi justru lebih sering salah di sini. Periksa kunci dan kalimat soal.';
            $perhatian = true;
        } elseif ($dp !== null && $dp < 0.20) {
            $catatan[] = 'Daya pembeda rendah: soal ini kurang membedakan siswa yang paham dan yang belum.';
        }

        if ($n && $p < 0.30) {
            $catatan[] = 'Sangat sedikit yang benar. Cek apakah materinya sudah diajarkan atau soalnya membingungkan.';
        }

        return [
            'id'             => $soal->id,
            'no'             => $no,
            'tipe'           => $soal->tipe,
            'bobot'          => $bobot,
            'teks'           => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $soal->teks))), 90),
            'p'              => round($p, 2),
            'persen'         => (int) round($p * 100),
            'kesukaran'      => self::kesukaran($p),
            'dp'             => $dp,
            'dpLabel'        => self::labelDayaPembeda($dp),
            'opsi'           => $opsi,
            'kosong'         => $kosong,
            'salahTerbanyak' => $salahTerbanyak,
            'catatan'        => $catatan,
            'perhatian'      => $perhatian,
        ];
    }

    private static function median(array $urut): float
    {
        $n = count($urut);
        if ($n === 0) {
            return 0.0;
        }

        $t = intdiv($n, 2);

        return $n % 2 ? $urut[$t] : ($urut[$t - 1] + $urut[$t]) / 2;
    }

    /** Tabel untuk ekspor Excel: satu baris per soal. */
    public static function tabelExport(array $h): array
    {
        $judul = ['No', 'Soal', 'Tipe', 'Bobot', '% Benar', 'Kesukaran', 'Daya Pembeda', 'Kategori DP', 'Catatan'];
        $baris = array_map(fn ($s) => [
            $s['no'],
            $s['teks'],
            $s['tipe'],
            $s['bobot'],
            $s['persen'],
            $s['kesukaran'],
            $s['dp'] ?? '',
            $s['dpLabel'],
            implode(' ', $s['catatan']),
        ], $h['soal']);

        return [$judul, $baris];
    }
}