<?php

namespace App\Services;

use App\Models\Soal;
use App\Models\Ujian;

/**
 * Mencari pasangan siswa yang jawabannya mirip. Hasilnya PETUNJUK untuk ditanyakan ke siswa,
 * bukan bukti kecurangan: siswa yang belajar bersama atau salah paham di hal yang sama juga bisa mirip.
 *
 * Tiga jenis petunjuk:
 *  - Pilihan ganda: dua siswa sama-sama salah dan memilih jawaban salah yang SAMA di banyak soal.
 *    Jawaban benar tidak dihitung (semua orang boleh sama-sama benar). Memakai huruf asli pilihan,
 *    jadi tetap sahih walaupun urutan pilihan diacak per siswa.
 *  - Isian: jawaban salah yang sama persis.
 *  - Uraian: teks yang mirip (kemiripan potongan tiga kata berurutan).
 */
class KemiripanJawaban
{
    public const AMBANG_PG        = 0.75;  // porsi jawaban salah yang sama, dari soal yang sama-sama salah
    public const AMBANG_ESAI      = 0.70;  // kemiripan teks uraian (0 sampai 1)
    public const MIN_SALAH_PG     = 4;     // minimal soal yang sama-sama salah agar PG dinilai
    public const MIN_ISIAN        = 2;     // minimal isian salah-sama agar pasangan ditampilkan (kalau hanya itu buktinya)
    public const MIN_KATA_ESAI    = 8;     // uraian lebih pendek dari ini tidak dibandingkan

    // "Fotosintesis, Itu!" -> "fotosintesis itu"
    public static function bersih(?string $teks): string
    {
        $t = mb_strtolower((string) $teks);
        $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $t);

        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    // himpunan potongan tiga kata berurutan (kurang dari tiga kata: kata tunggal)
    public static function potongan(string $bersih): array
    {
        $kata = $bersih === '' ? [] : explode(' ', $bersih);
        $n    = count($kata);

        if ($n < 3) {
            return array_fill_keys($kata, true);
        }

        $out = [];
        for ($i = 0; $i <= $n - 3; $i++) {
            $out[$kata[$i].' '.$kata[$i + 1].' '.$kata[$i + 2]] = true;
        }

        return $out;
    }

    // 0 sampai 1
    public static function kemiripanTeks(string $a, string $b): float
    {
        $pa = self::potongan(self::bersih($a));
        $pb = self::potongan(self::bersih($b));

        if (! $pa || ! $pb) {
            return 0.0;
        }

        $irisan = count(array_intersect_key($pa, $pb));
        $gabung = count($pa) + count($pb) - $irisan;

        return $gabung ? $irisan / $gabung : 0.0;
    }

    public static function jumlahKata(?string $teks): int
    {
        $b = self::bersih($teks);

        return $b === '' ? 0 : count(explode(' ', $b));
    }

    /**
     * @return array{jumlah:int, pasangan:array<int,array>, ambang:array}
     */
    public static function cari(
        Ujian $ujian,
        float $ambangPg = self::AMBANG_PG,
        float $ambangEsai = self::AMBANG_ESAI,
    ): array {
        $soals = $ujian->soals()->get();
        $nomor = [];
        foreach ($soals as $i => $s) {
            $nomor[$s->id] = $i + 1;
        }

        $pengerjaans = $ujian->pengerjaans()->where('status', 'selesai')
            ->with(['siswa', 'jawabans'])->get()->filter(fn ($p) => $p->siswa)->values();

        // per siswa: jawaban per soal
        $data = [];
        foreach ($pengerjaans as $p) {
            $data[$p->id] = $p->jawabans->keyBy('soal_id')->map(fn ($j) => (string) $j->jawaban)->all();
        }

        $pasangan = [];
        $n = $pengerjaans->count();

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $pengerjaans[$i];
                $b = $pengerjaans[$j];

                $pg = ['sama' => 0, 'dua_salah' => 0, 'soal' => []];
                $isian = [];
                $esai = [];

                foreach ($soals as $s) {
                    $ja = $data[$a->id][$s->id] ?? '';
                    $jb = $data[$b->id][$s->id] ?? '';

                    if (trim($ja) === '' || trim($jb) === '') {
                        continue;
                    }

                    if ($s->tipe === 'pg') {
                        if (! KoreksiUjian::cocok($s, $ja) && ! KoreksiUjian::cocok($s, $jb)) {
                            $pg['dua_salah']++;
                            if (strtoupper(trim($ja)) === strtoupper(trim($jb))) {
                                $pg['sama']++;
                                $pg['soal'][] = $nomor[$s->id];
                            }
                        }
                    } elseif ($s->tipe === 'isian') {
                        if (! KoreksiUjian::cocok($s, $ja) && ! KoreksiUjian::cocok($s, $jb)
                            && KoreksiUjian::normal($ja) === KoreksiUjian::normal($jb)) {
                            $isian[] = ['no' => $nomor[$s->id], 'jawaban' => trim($ja)];
                        }
                    } elseif ($s->tipe === 'esai') {
                        if (self::jumlahKata($ja) >= self::MIN_KATA_ESAI && self::jumlahKata($jb) >= self::MIN_KATA_ESAI) {
                            $mirip = self::kemiripanTeks($ja, $jb);
                            if ($mirip >= $ambangEsai) {
                                $esai[] = ['no' => $nomor[$s->id], 'persen' => (int) round($mirip * 100), 'a' => $ja, 'b' => $jb];
                            }
                        }
                    }
                }

                $pgLolos = $pg['dua_salah'] >= self::MIN_SALAH_PG && $pg['sama'] / $pg['dua_salah'] >= $ambangPg;

                if (! $pgLolos && count($isian) < self::MIN_ISIAN && ! $esai) {
                    continue;
                }

                $poin = ($pgLolos ? $pg['sama'] : 0) + 2 * count($isian) + 3 * count($esai);

                $pasangan[] = [
                    'a'      => ['pid' => $a->id, 'nama' => $a->siswa->nama],
                    'b'      => ['pid' => $b->id, 'nama' => $b->siswa->nama],
                    'pg'     => $pgLolos ? $pg : null,
                    'isian'  => $isian,
                    'esai'   => $esai,
                    'poin'   => $poin,
                ];
            }
        }

        usort($pasangan, fn ($x, $y) => $y['poin'] <=> $x['poin'] ?: strcmp($x['a']['nama'], $y['a']['nama']));

        return [
            'jumlah'   => $n,
            'pasangan' => $pasangan,
            'ambang'   => ['pg' => $ambangPg, 'esai' => $ambangEsai],
        ];
    }
}
