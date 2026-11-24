<?php

namespace App\Services;

use App\Models\NilaiDetail;
use App\Models\NilaiSiswa;
use App\Models\Pengerjaan;
use App\Models\Ujian;
use Illuminate\Support\Facades\DB;

/**
 * Remedial berbasis ujian CBT: membuat ujian remedial (draf, peserta terbatas) dari ujian asal,
 * lalu menerapkan nilai remedial ke nilai akhir di Penilaian dengan aturan yang dipilih guru.
 * Nilai asli SELALU dibaca dari pengerjaan ujian asal, jadi menerapkan berulang kali hasilnya sama.
 */
class RemedialUjian
{
    public const ATURAN = [
        'kkm'       => 'Dibatasi KKM (nilai remedial paling tinggi sama dengan KKM)',
        'tertinggi' => 'Ambil yang tertinggi (nilai asli atau remedial)',
        'rata'      => 'Rata-rata nilai asli dan remedial (tidak di bawah nilai asli)',
    ];

    public static function akhir(float $asli, float $remedial, string $aturan, int $kkm): float
    {
        $nilai = match ($aturan) {
            'tertinggi' => max($asli, $remedial),
            'rata'      => max($asli, ($asli + $remedial) / 2),
            default     => max($asli, min($remedial, (float) $kkm)),
        };

        return round($nilai, 2);
    }

    /** Buat ujian remedial berstatus draft. Soal memakai bobot yang sama dengan ujian asal. */
    public static function buat(Ujian $asal, array $soalIds, array $siswaIds, int $durasi): Ujian
    {
        return DB::transaction(function () use ($asal, $soalIds, $siswaIds, $durasi) {
            $remedial = Ujian::create([
                'guru_id'          => $asal->guru_id,
                'kelas_id'         => $asal->kelas_id,
                'mapel_id'         => $asal->mapel_id,
                'judul'            => 'Remedial: '.preg_replace('/^Remedial( \d+)?: /u', '', $asal->judul),
                'durasi'           => $durasi,
                'token'            => Ujian::tokenBaru(),
                'status'           => 'draft',
                'acak_soal'        => true,
                'acak_opsi'        => true,
                'tampil_nilai'     => (bool) $asal->tampil_nilai,
                'remedial_dari_id' => $asal->id,
            ]);

            $bobot = $asal->soals()->get()->mapWithKeys(fn ($s) => [$s->id => (int) $s->pivot->bobot]);
            $sync  = [];
            $urut  = 0;
            foreach ($asal->soals()->get() as $s) {
                if (in_array($s->id, $soalIds, true)) {
                    $sync[$s->id] = ['urutan' => $urut++, 'bobot' => $bobot[$s->id]];
                }
            }
            $remedial->soals()->sync($sync);
            $remedial->pesertas()->sync($siswaIds);

            return $remedial;
        });
    }

    /**
     * Perbandingan nilai asli, remedial (yang tertinggi dari semua ujian remedial), dan nilai akhir.
     * @return array<int, array{siswa:\App\Models\Siswa, asli:float, remedial:float, akhir:float}>
     */
    public static function banding(Ujian $asal, string $aturan, int $kkm): array
    {
        $asal = $asal->isRemedial() ? $asal->asal : $asal;

        $remedialIds = $asal->remedials()->pluck('id');
        $rem = Pengerjaan::whereIn('ujian_id', $remedialIds)->where('status', 'selesai')->where('perlu_koreksi', false)
            ->with('siswa')->get()->groupBy('siswa_id');

        $baris = [];
        foreach ($rem as $siswaId => $kumpulan) {
            $asli = Pengerjaan::where('ujian_id', $asal->id)->where('siswa_id', $siswaId)->where('status', 'selesai')->value('nilai');
            if ($asli === null) {
                continue;
            }
            $terbaik = (float) $kumpulan->max('nilai');
            $baris[] = [
                'siswa'    => $kumpulan->first()->siswa,
                'asli'     => (float) $asli,
                'remedial' => $terbaik,
                'akhir'    => self::akhir((float) $asli, $terbaik, $aturan, $kkm),
            ];
        }

        usort($baris, fn ($a, $b) => strcmp($a['siswa']->nama, $b['siswa']->nama));

        return $baris;
    }

    /** @return array{galat:?string, diterapkan:int} */
    public static function terapkan(Ujian $ujian, string $aturan, int $kkm): array
    {
        $asal = $ujian->isRemedial() ? $ujian->asal : $ujian;

        if (! array_key_exists($aturan, self::ATURAN)) {
            return ['galat' => 'Aturan nilai tidak dikenal.', 'diterapkan' => 0];
        }
        if (! $asal->penilaian_id || ! $asal->penilaian) {
            return ['galat' => 'Nilai ujian asli belum dikirim ke Penilaian. Kirim dulu lewat halaman hasil ujian aslinya (kalau Penilaian sebelumnya sudah dihapus, kirim ulang akan membuatnya lagi), baru terapkan remedial.', 'diterapkan' => 0];
        }

        $baris = self::banding($asal, $aturan, $kkm);
        if (! $baris) {
            return ['galat' => 'Belum ada siswa yang menyelesaikan remedial.', 'diterapkan' => 0];
        }

        $penilaian = $asal->penilaian;
        $kriteria  = $penilaian->kriterias()->first();

        DB::transaction(function () use ($baris, $penilaian, $kriteria, $aturan) {
            foreach ($baris as $b) {
                $ns = NilaiSiswa::firstOrNew(['penilaian_id' => $penilaian->id, 'siswa_id' => $b['siswa']->id]);

                // catatan guru dipertahankan; hanya bagian [Remedial: ...] yang diganti
                $lama = trim(preg_replace('/\s*\[Remedial:[^\]]*\]/u', '', (string) $ns->catatan));
                $ns->total   = $b['akhir'];
                $ns->catatan = trim($lama.' [Remedial: asli '.self::angka($b['asli']).', remedial '.self::angka($b['remedial']).', akhir '.self::angka($b['akhir']).' ('.$aturan.')]');
                $ns->save();

                if ($kriteria) {
                    NilaiDetail::updateOrCreate(['nilai_siswa_id' => $ns->id, 'kriteria_id' => $kriteria->id], ['nilai' => $b['akhir']]);
                }
            }
        });

        return ['galat' => null, 'diterapkan' => count($baris)];
    }

    private static function angka(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}