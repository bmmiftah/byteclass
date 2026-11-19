<?php

namespace App\Services;

class HitungNilai
{
    // Ubah nilai satu kriteria menjadi skala 0-100
    public static function normal(string $tipe, float $maks, float $nilai): float
    {
        if ($tipe === 'ceklis') {
            return $nilai > 0 ? 100.0 : 0.0;
        }

        if ($maks <= 0) {
            return 0.0;
        }

        return max(0.0, min($nilai, $maks)) / $maks * 100;
    }

    /**
     * @param iterable $kriterias model kriteria (punya id, tipe, maks, bobot)
     * @param array $detail [id_kriteria => nilai]
     */
    public static function total(iterable $kriterias, array $detail): float
    {
        $jumlahBobot = 0;
        $skor = 0.0;

        foreach ($kriterias as $k) {
            $jumlahBobot += $k->bobot;
            $skor += self::normal($k->tipe, (float) $k->maks, (float) ($detail[$k->id] ?? 0)) * $k->bobot;
        }

        return $jumlahBobot > 0 ? round($skor / $jumlahBobot, 2) : 0.0;
    }
}