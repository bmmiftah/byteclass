<?php

namespace App\Services;

use App\Models\Jawaban;
use App\Models\Pengerjaan;
use App\Models\Soal;
use App\Models\Ujian;
use Illuminate\Support\Facades\DB;

class KoreksiUjian
{
    // "  Fotosintesis  " -> "fotosintesis"
    public static function normal(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));

        return (string) preg_replace('/\s+/u', ' ', $s);
    }

    // Jawaban otomatis benar? (tidak berlaku untuk uraian)
    public static function cocok(Soal $soal, ?string $jawab): bool
    {
        if ($soal->tipe === 'pg') {
            $kunci = strtoupper(trim((string) $soal->kunci));
            $pilihan = strtoupper(trim((string) $jawab));

            return $pilihan !== '' && $pilihan === $kunci;
        }

        $j = self::normal($jawab);
        if ($j === '') {
            return false;
        }

        foreach (explode('|', (string) $soal->kunci) as $k) {
            if (self::normal($k) === $j) {
                return true;
            }
        }

        return false;
    }

    // Kunci pengerjaan: koreksi otomatis, lalu hitung nilai
    public static function selesaikan(Pengerjaan $p): void
    {
        if ($p->status === 'selesai') {
            return;
        }

        DB::transaction(function () use ($p) {
            $ujian = $p->ujian()->first();
            $soals = $ujian->soals()->get();
            $jawabans = $p->jawabans()->get()->keyBy('soal_id');

            foreach ($soals as $soal) {
                $j = $jawabans[$soal->id] ?? new Jawaban([
                    'pengerjaan_id' => $p->id,
                    'soal_id' => $soal->id,
                ]);

                if ($soal->tipe === 'esai') {
                    $j->benar = null;
                    // Uraian kosong langsung 0; yang berisi menunggu koreksi guru
                    $j->skor = trim((string) $j->jawaban) === '' ? 0 : null;
                } else {
                    $j->benar = self::cocok($soal, $j->jawaban);
                    $j->skor = $j->benar ? (int) $soal->pivot->bobot : 0;
                }

                $j->save();
            }

            $selesai = now()->lessThan($p->batas_pada) ? now() : $p->batas_pada;

            $p->forceFill([
                'status' => 'selesai',
                'selesai_pada' => $selesai,
            ])->save();

            self::hitung($p);
        });
    }

    // Hitung ulang skor dan nilai (0-100) dari jawaban yang ada
    public static function hitung(Pengerjaan $p): void
    {
        $ujian = $p->ujian()->first();
        $bobot = $ujian->soals()->get()->mapWithKeys(fn ($s) => [$s->id => (int) $s->pivot->bobot]);
        $total = $bobot->sum();

        $jawabans = $p->jawabans()->get()->filter(fn ($j) => $bobot->has($j->soal_id));
        $skor = (float) $jawabans->sum(fn ($j) => (float) ($j->skor ?? 0));
        $perlu = $jawabans->contains(fn ($j) => $j->skor === null);

        $p->forceFill([
            'skor' => $skor,
            'nilai' => $total > 0 ? round($skor / $total * 100, 2) : 0,
            'perlu_koreksi' => $perlu,
        ])->save();
    }

    // Pengerjaan yang waktunya habis tapi belum dikirim siswa (tab ditutup, HP mati)
    public static function rapikanKedaluwarsa(Ujian $ujian): void
    {
        $ujian->pengerjaans()
            ->where('status', 'berjalan')
            ->where('batas_pada', '<', now())
            ->get()
            ->each(fn ($p) => self::selesaikan($p));
    }
}