<?php

namespace App\Services;

use App\Models\Pengerjaan;
use App\Models\PengerjaanKejadian;
use Illuminate\Support\Facades\DB;

/**
 * Catatan kejadian selama siswa mengerjakan ujian, hitungan pelanggaran,
 * dan tindakan saat batas pelanggaran tercapai (kunci atau kumpul otomatis).
 */
class Anticurang
{
    // jenis kejadian yang boleh dikirim browser siswa
    public const DARI_SISWA = [
        'keluar_tab'         => 'Pindah tab atau aplikasi',
        'blur'               => 'Jendela ujian tidak aktif',
        'keluar_layar_penuh' => 'Keluar dari layar penuh',
        'salin'              => 'Mencoba menyalin',
        'potong'             => 'Mencoba memotong',
        'tempel'             => 'Mencoba menempel',
        'klik_kanan'         => 'Klik kanan',
        'cetak'              => 'Mencoba mencetak',
        'kembali'            => 'Kembali ke halaman ujian',
    ];

    // jenis yang dicatat server sendiri
    public const DARI_SISTEM = [
        'kunci'           => 'Ujian dikunci (batas pelanggaran)',
        'buka_kunci'      => 'Kunci dibuka guru',
        'kumpul_otomatis' => 'Dikumpulkan otomatis (batas pelanggaran)',
    ];

    // hanya tiga ini yang menambah hitungan pelanggaran
    public const DIHITUNG = ['keluar_tab', 'blur', 'keluar_layar_penuh'];

    // dua kejadian yang dihitung dalam selang ini dianggap satu (pindah tab memicu keluar_tab dan blur sekaligus)
    public const JEDA_DETIK = 3;

    // batas jumlah baris catatan per pengerjaan, supaya tabel tidak dibanjiri
    public const MAKS_CATATAN = 500;

    public static function label(string $jenis): string
    {
        return self::DARI_SISWA[$jenis] ?? self::DARI_SISTEM[$jenis] ?? $jenis;
    }

    // sisa kesempatan sebelum kena batas; null kalau tidak ada batas
    public static function sisaKesempatan(Pengerjaan $p): ?int
    {
        $ujian = $p->ujian;

        if (! $ujian || ! $ujian->punyaBatas()) {
            return null;
        }

        return max(0, (int) $ujian->batas_pelanggaran - ((int) $p->pelanggaran - (int) $p->dasar_batas));
    }

    // keadaan yang dikirim ke browser siswa
    public static function keadaan(Pengerjaan $p, bool $dihitung = false): array
    {
        return [
            'pelanggaran'   => (int) $p->pelanggaran,
            'batas'         => $p->ujian?->punyaBatas() ? (int) $p->ujian->batas_pelanggaran : null,
            'sisa'          => self::sisaKesempatan($p),
            'aksi'          => $p->ujian?->aksi_batas,
            'terkunci'      => $p->terkunci(),
            'selesai'       => $p->status === 'selesai',
            'dihitung'      => $dihitung,
        ];
    }

    /**
     * Catat satu kejadian dari siswa. Mengembalikan keadaan terbaru.
     * Hanya pengerjaan yang sedang berjalan dan tidak terkunci yang dicatat.
     */
    public static function catat(Pengerjaan $p, string $jenis, ?string $detail = null): array
    {
        if (! array_key_exists($jenis, self::DARI_SISWA)) {
            return self::keadaan($p);
        }

        return DB::transaction(function () use ($p, $jenis, $detail) {
            $p = Pengerjaan::with('ujian')->whereKey($p->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== 'berjalan' || $p->terkunci()) {
                return self::keadaan($p);
            }

            $dihitung = in_array($jenis, self::DIHITUNG, true);

            if ($dihitung) {
                $baruSaja = PengerjaanKejadian::where('pengerjaan_id', $p->id)
                    ->where('dihitung', true)
                    ->where('terjadi_pada', '>=', now()->subSeconds(self::JEDA_DETIK))
                    ->exists();

                if ($baruSaja) {
                    return self::keadaan($p);   // kembaran dari kejadian yang barusan dicatat
                }
            } elseif (PengerjaanKejadian::where('pengerjaan_id', $p->id)->count() >= self::MAKS_CATATAN) {
                return self::keadaan($p);
            }

            PengerjaanKejadian::create([
                'pengerjaan_id' => $p->id,
                'jenis'         => $jenis,
                'detail'        => $detail !== null ? mb_substr($detail, 0, 120) : null,
                'dihitung'      => $dihitung,
                'terjadi_pada'  => now(),
            ]);

            if ($dihitung) {
                $p->increment('pelanggaran');
                $p->refresh();
                self::periksaBatas($p);
                $p->refresh();
            }

            return self::keadaan($p, $dihitung);
        });
    }

    // sudah melewati batas? kunci atau kumpulkan otomatis
    private static function periksaBatas(Pengerjaan $p): void
    {
        $ujian = $p->ujian;

        if (! $ujian->punyaBatas() || self::sisaKesempatan($p) > 0) {
            return;
        }

        $p->forceFill(['kena_batas' => true])->save();

        if ($ujian->aksi_batas === 'kumpul') {
            self::sistem($p, 'kumpul_otomatis');
            KoreksiUjian::selesaikan($p);

            return;
        }

        $p->forceFill(['terkunci_pada' => now()])->save();
        self::sistem($p, 'kunci');
    }

    // guru membuka kunci: siswa mendapat jatah baru sebanyak batas, riwayat pelanggaran tetap
    public static function bukaKunci(Pengerjaan $p, ?string $oleh = null): bool
    {
        if (! $p->terkunci() || $p->status !== 'berjalan') {
            return false;
        }

        $p->forceFill(['terkunci_pada' => null, 'dasar_batas' => $p->pelanggaran])->save();
        self::sistem($p, 'buka_kunci', $oleh ? 'oleh '.$oleh : null);

        return true;
    }

    private static function sistem(Pengerjaan $p, string $jenis, ?string $detail = null): void
    {
        PengerjaanKejadian::create([
            'pengerjaan_id' => $p->id,
            'jenis'         => $jenis,
            'detail'        => $detail,
            'dihitung'      => false,
            'terjadi_pada'  => now(),
        ]);
    }

    // jumlah per jenis, untuk ringkasan di halaman guru
    public static function ringkasan(Pengerjaan $p): array
    {
        return $p->kejadians()->get()->groupBy('jenis')->map->count()->all();
    }
}
