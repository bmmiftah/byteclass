<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\TugasPengumpulan;
use App\Models\Ujian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Daftar pengingat yang berlaku SEKARANG untuk seorang siswa.
 * Dipakai dua tempat: kartu "Segera" di beranda dan perintah pengingat:kirim (notifikasi push).
 *
 * Aturan:
 *  - tugas: belum dikumpulkan, masih bisa dikumpulkan, dan tenggat <= 24 jam lagi ("24j") atau <= 3 jam lagi ("3j").
 *      Yang berlaku adalah jendela yang paling mendesak.
 *  - ujian: dibuka dalam <= 60 menit ke depan.
 */
class PengingatSiswa
{
    /** @return Collection<int, array{kunci:string, jenis:string, judul:string, isi:string, url:string, waktu:Carbon, mendesak:bool}> */
    public static function untuk(Siswa $siswa, ?Carbon $sekarang = null): Collection
    {
        $sekarang = ($sekarang ?? now())->copy();
        $kelasId = $siswa->kelas_id;
        $hasil = collect();

        $jendela = config('pengingat.tugas'); // ['3j' => 180, '24j' => 1440]
        asort($jendela); // paling mendesak dulu
        $terjauh = max($jendela);

        $tugas = Tugas::terlihat()
            ->where('kelas_id', $kelasId)
            ->where('status', 'terbit')
            ->where('tenggat', '>', $sekarang)
            ->where('tenggat', '<=', $sekarang->copy()->addMinutes($terjauh))
            ->orderBy('tenggat')
            ->get();

        $sudah = TugasPengumpulan::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $tugas->pluck('id'))
            ->pluck('tugas_id')
            ->all();

        foreach ($tugas as $t) {
            if (in_array($t->id, $sudah, true) || ! $t->bolehKumpul()) {
                continue;
            }

            $menit = $sekarang->diffInMinutes($t->tenggat, false);

            foreach ($jendela as $nama => $batas) {
                if ($menit <= $batas) {
                    $hasil->push([
                        'kunci'    => "tugas:{$t->id}:{$nama}",
                        'jenis'    => 'tugas',
                        'judul'    => 'Tugas segera berakhir',
                        'isi'      => $t->judul . ' (' . $t->sisaWaktu() . ')',
                        'url'      => route('saya.tugas.show', $t->id, false),
                        'waktu'    => $t->tenggat,
                        'mendesak' => $batas <= 180,
                    ]);
                    break;
                }
            }
        }

        $menitUjian = (int) config('pengingat.ujian_menit');
        $ujians = Ujian::untukSiswa($siswa->id)
            ->where('kelas_id', $kelasId)
            ->where('status', 'buka')
            ->whereNotNull('buka_pada')
            ->where('buka_pada', '>', $sekarang)
            ->where('buka_pada', '<=', $sekarang->copy()->addMinutes($menitUjian))
            ->orderBy('buka_pada')
            ->get();

        foreach ($ujians as $u) {
            $hasil->push([
                'kunci'    => "ujian:{$u->id}:mulai",
                'jenis'    => 'ujian',
                'judul'    => 'Ujian sebentar lagi',
                'isi'      => $u->judul . ' dibuka pukul ' . $u->buka_pada->format('H.i'),
                'url'      => route('cbt.daftar', [], false),
                'waktu'    => $u->buka_pada,
                'mendesak' => true,
            ]);
        }

        return $hasil->sortBy(fn ($p) => $p['waktu']->timestamp)->values();
    }
}