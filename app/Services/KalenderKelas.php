<?php

namespace App\Services;

use App\Models\Agenda;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\TugasPengumpulan;
use App\Models\Ujian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menggabungkan tenggat tugas, jadwal ujian, dan agenda guru menjadi satu daftar kejadian per hari,
 * dipakai oleh beranda, agenda, dan kalender siswa.
 */
class KalenderKelas
{
    /** jenis => [label, warna] */
    public const JENIS = [
        'tugas'    => ['Tugas', 'var(--kuning)'],
        'ujian'    => ['Ujian', 'var(--pink)'],
        'kegiatan' => ['Kegiatan', 'var(--biru)'],
        'libur'    => ['Libur', 'var(--hijau)'],
        'penting'  => ['Penting', 'var(--oranye)'],
    ];

    public const HARI = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    /**
     * Semua kejadian kelas antara dua waktu, terurut. Acara beberapa hari dipecah per hari.
     * Bila $siswa diberikan, tugas ditandai "selesai" kalau siswa itu sudah mengumpulkan.
     */
    public static function kejadian(Kelas $kelas, Carbon $dari, Carbon $sampai, ?Siswa $siswa = null): Collection
    {
        $dari = $dari->copy()->startOfDay();
        $sampai = $sampai->copy()->endOfDay();
        $hasil = collect();

        // Tenggat tugas
        $tugas = Tugas::terlihat()
            ->where('kelas_id', $kelas->id)
            ->whereBetween('tenggat', [$dari, $sampai])
            ->get();

        $sudah = $siswa
            ? TugasPengumpulan::where('siswa_id', $siswa->id)
                ->whereIn('tugas_id', $tugas->pluck('id'))
                ->pluck('tugas_id')
                ->all()
            : [];

        foreach ($tugas as $t) {
            $hasil->push(self::entri('tugas', 'Tenggat: ' . $t->judul, $t->tenggat, false, [
                'url'     => $siswa ? route('saya.tugas.show', $t->id) : null,
                'selesai' => $siswa ? in_array($t->id, $sudah, true) : null,
            ]));
        }

        // Jadwal ujian (hanya yang punya waktu buka dan sudah dibuka untuk siswa)
        $ujians = Ujian::untukSiswa($siswa?->id)
            ->where('kelas_id', $kelas->id)
            ->whereIn('status', ['buka', 'tutup'])
            ->whereNotNull('buka_pada')
            ->whereBetween('buka_pada', [$dari, $sampai])
            ->get();

        foreach ($ujians as $u) {
            $keterangan = $u->durasi . ' menit'
                . ($u->tutup_pada ? ', ditutup ' . $u->tutup_pada->locale('id')->translatedFormat('j F H.i') : '');

            $hasil->push(self::entri('ujian', 'Ujian: ' . $u->judul, $u->buka_pada, false, [
                'url'        => $siswa ? route('cbt.daftar') : null,
                'keterangan' => $keterangan,
            ]));
        }

        // Agenda guru; acara beberapa hari muncul di setiap harinya
        $agendas = Agenda::where('kelas_id', $kelas->id)
            ->where('mulai', '<=', $sampai)
            ->get()
            ->filter(fn ($a) => $a->akhir->copy()->endOfDay()->gte($dari));

        foreach ($agendas as $a) {
            $hari = $a->mulai->copy()->startOfDay();
            if ($hari->lt($dari)) {
                $hari = $dari->copy();
            }

            $akhir = $a->akhir->copy()->startOfDay();
            if ($akhir->gt($sampai)) {
                $akhir = $sampai->copy()->startOfDay();
            }

            for ($i = 0; $hari->lte($akhir) && $i < 62; $i++, $hari->addDay()) {
                $awal = $hari->isSameDay($a->mulai);

                $hasil->push(self::entri(
                    $a->jenis,
                    $a->judul,
                    $awal ? $a->mulai : $hari->copy(),
                    $a->seharian || ! $awal,
                    [
                        'keterangan' => $a->keterangan,
                        'lanjutan'   => ! $awal,
                    ]
                ));
            }
        }

        return $hasil->sortBy(
            fn ($e) => $e['tanggal'] . ($e['jam'] === null ? '0' : '1') . $e['jam'] . $e['judul']
        )->values();
    }

    private static function entri(string $jenis, string $judul, Carbon $waktu, bool $seharian, array $lebih = []): array
    {
        [$label, $warna] = self::JENIS[$jenis] ?? self::JENIS['kegiatan'];

        return array_merge([
            'jenis'      => $jenis,
            'label'      => $label,
            'warna'      => $warna,
            'judul'      => $judul,
            'tanggal'    => $waktu->format('Y-m-d'),
            'jam'        => $seharian ? null : $waktu->format('H.i'),
            'keterangan' => null,
            'url'        => null,
            'selesai'    => null,
            'lanjutan'   => false,
        ], $lebih);
    }

    /** "2026-11" -> Carbon awal bulan; nilai aneh atau terlalu jauh jatuh ke bulan ini */
    public static function bulanDari(mixed $teks): Carbon
    {
        $sekarang = now()->startOfMonth();

        if (! is_string($teks) || ! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $teks, $m)) {
            return $sekarang;
        }

        $bulan = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();

        return abs($bulan->diffInMonths($sekarang, false)) > 36 ? $sekarang : $bulan;
    }

    /** Kisi kalender Senin-Minggu untuk satu bulan: daftar minggu, tiap minggu 7 hari */
    public static function grid(Carbon $bulan, Collection $kejadian): array
    {
        $perHari = $kejadian->groupBy('tanggal');
        $awal = $bulan->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $akhir = $bulan->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $minggu = [];

        for ($d = $awal->copy(); $d->lte($akhir); $d->addDay()) {
            $k = $d->format('Y-m-d');
            $minggu[(int) floor($awal->diffInDays($d) / 7)][] = [
                'tgl'      => $d->copy(),
                'kunci'    => $k,
                'diBulan'  => $d->month === $bulan->month,
                'hariIni'  => $d->isToday(),
                'kejadian' => $perHari->get($k, collect())->values()->all(),
            ];
        }

        return array_values($minggu);
    }

    /** Tanggal ke teks Indonesia: "Senin, 23 November 2026" */
    public static function tanggalTeks(string|Carbon $tgl): string
    {
        return Carbon::parse($tgl)->locale('id')->translatedFormat('l, j F Y');
    }
}