<?php

namespace App\Services;

use App\Models\Agenda;
use App\Models\AkunSiswa;
use App\Models\Tugas;
use App\Models\TugasPengumpulan;
use App\Models\Ujian;
use Illuminate\Support\Carbon;

/** Mengubah tugas, ujian, dan agenda kelas seorang siswa menjadi isi berkas .ics. */
class KalenderIcs
{
    /** Hanya kejadian yang belum lewat lebih dari ini yang ikut (supaya berkas tidak membengkak). */
    public const HARI_LALU = 30;

    public const HARI_DEPAN = 400;

    public const MAKS = 800;

    public static function untuk(AkunSiswa $akun, ?Carbon $sekarang = null): string
    {
        $sekarang = $sekarang ?? now();
        $siswa = $akun->siswa;
        $kelas = $siswa->kelas;

        $dari = $sekarang->copy()->subDays(self::HARI_LALU)->startOfDay();
        $sampai = $sekarang->copy()->addDays(self::HARI_DEPAN)->endOfDay();
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'asisten-guru';
        $acara = [];

        // Tenggat tugas: dengan alarm 1 hari dan 2 jam sebelumnya, kecuali sudah dikumpulkan
        $tugas = Tugas::terlihat()
            ->where('kelas_id', $kelas->id)
            ->whereBetween('tenggat', [$dari, $sampai])
            ->orderBy('tenggat')
            ->get();

        $sudah = TugasPengumpulan::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $tugas->pluck('id'))
            ->pluck('tugas_id')
            ->all();

        foreach ($tugas as $t) {
            $selesai = in_array($t->id, $sudah, true);
            $acara[] = [
                'uid'        => "tugas-{$t->id}-{$siswa->id}@{$host}",
                'judul'      => ($selesai ? 'Sudah dikumpulkan: ' : 'Tenggat: ') . $t->judul,
                'mulai'      => $t->tenggat,
                'selesai'    => null,
                'seharian'   => false,
                'keterangan' => 'Tugas ' . $kelas->nama,
                'url'        => route('saya.tugas.show', $t->id),
                'alarm'      => $selesai ? [] : [1440, 120],
            ];
        }

        // Jadwal ujian
        $ujians = Ujian::untukSiswa($siswa->id)
            ->where('kelas_id', $kelas->id)
            ->whereIn('status', ['buka', 'tutup'])
            ->whereNotNull('buka_pada')
            ->whereBetween('buka_pada', [$dari, $sampai])
            ->orderBy('buka_pada')
            ->get();

        foreach ($ujians as $u) {
            $acara[] = [
                'uid'        => "ujian-{$u->id}@{$host}",
                'judul'      => 'Ujian: ' . $u->judul,
                'mulai'      => $u->buka_pada,
                'selesai'    => $u->tutup_pada ?: $u->buka_pada->copy()->addMinutes(max(15, (int) $u->durasi)),
                'seharian'   => false,
                'keterangan' => 'Durasi ' . $u->durasi . ' menit',
                'url'        => route('cbt.daftar'),
                'alarm'      => [60],
            ];
        }

        // Agenda guru
        $agendas = Agenda::where('kelas_id', $kelas->id)
            ->where('mulai', '<=', $sampai)
            ->orderBy('mulai')
            ->get()
            ->filter(fn ($a) => $a->akhir->copy()->endOfDay()->gte($dari));

        foreach ($agendas as $a) {
            $acara[] = [
                'uid'        => "agenda-{$a->id}@{$host}",
                'judul'      => $a->judul,
                'mulai'      => $a->mulai,
                'selesai'    => $a->selesai,
                'seharian'   => (bool) $a->seharian,
                'keterangan' => $a->keterangan,
                'url'        => null,
                'alarm'      => [],
            ];
        }

        return IcsPembangun::buat('Kelas ' . $kelas->nama, array_slice($acara, 0, self::MAKS), $sekarang);
    }
}