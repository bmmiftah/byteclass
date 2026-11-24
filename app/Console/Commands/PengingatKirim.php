<?php

namespace App\Console\Commands;

use App\Models\AkunSiswa;
use App\Models\PengingatTerkirim;
use App\Services\PengingatSiswa;
use App\Services\PengirimWebPush;
use Illuminate\Console\Command;

/**
 * Mengirim notifikasi push pengingat tenggat tugas dan ujian ke HP siswa yang mengaktifkannya.
 * Dijalankan tiap beberapa menit lewat jadwal Laravel (routes/console.php). Aman diulang: pengingat yang
 * sama tidak dikirim dua kali.
 */
class PengingatKirim extends Command
{
    protected $signature = 'pengingat:kirim {--kering : hanya tampilkan, tidak mengirim}';

    protected $description = 'Kirim notifikasi pengingat tenggat tugas dan ujian ke siswa';

    public function handle(PengirimWebPush $pengirim): int
    {
        $kering = (bool) $this->option('kering');

        if (! $kering && ! $pengirim->siap()) {
            $this->warn('Kunci VAPID belum diisi di .env. Jalankan: php artisan pengingat:kunci');

            return self::SUCCESS;
        }

        $terkirim = 0;

        AkunSiswa::whereHas('pushes')->with(['pushes', 'siswa.kelas'])->chunkById(100, function ($akuns) use ($pengirim, $kering, &$terkirim) {
            foreach ($akuns as $akun) {
                if (! $akun->siswa || ! $akun->siswa->aktif) {
                    continue;
                }

                $daftar = PengingatSiswa::untuk($akun->siswa);
                $sudah = PengingatTerkirim::where('akun_siswa_id', $akun->id)
                    ->whereIn('kunci', $daftar->pluck('kunci'))
                    ->pluck('kunci')
                    ->all();

                foreach ($daftar->reject(fn ($p) => in_array($p['kunci'], $sudah, true)) as $p) {
                    if ($kering) {
                        $this->line("[kering] {$akun->siswa->nama}: {$p['judul']} - {$p['isi']}");
                        continue;
                    }

                    $berhasil = 0;

                    foreach ($akun->pushes as $langganan) {
                        $hasil = $pengirim->kirim($langganan, [
                            'judul' => $p['judul'],
                            'isi'   => $p['isi'],
                            'url'   => $p['url'],
                            'tag'   => $p['kunci'],
                            'badge' => $daftar->count(),
                        ], $p['mendesak'] ? 1800 : 7200);

                        if ($hasil === 'kadaluarsa') {
                            $langganan->delete();
                        } elseif ($hasil === 'ok') {
                            $berhasil++;
                            $langganan->forceFill(['terakhir_kirim' => now()])->save();
                        }
                    }

                    // Dicatat hanya kalau ada satu perangkat yang menerima; kalau semua gagal, dicoba lagi di putaran berikutnya
                    if ($berhasil > 0) {
                        PengingatTerkirim::insertOrIgnore([[
                            'akun_siswa_id' => $akun->id,
                            'kunci'         => $p['kunci'],
                            'dikirim_pada'  => now(),
                        ]]);
                        $terkirim++;
                    }
                }
            }
        });

        PengingatTerkirim::where('dikirim_pada', '<', now()->subDays((int) config('pengingat.simpan_hari')))->delete();

        $this->info($kering ? 'Selesai (mode kering).' : "Pengingat terkirim: {$terkirim}");

        return self::SUCCESS;
    }
}