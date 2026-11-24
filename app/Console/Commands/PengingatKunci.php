<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Membuat sepasang kunci VAPID untuk notifikasi push. Cukup dijalankan sekali. */
class PengingatKunci extends Command
{
    protected $signature = 'pengingat:kunci';

    protected $description = 'Buat kunci VAPID untuk notifikasi push (tempel hasilnya ke .env)';

    public function handle(): int
    {
        if (filled(config('pengingat.vapid.public'))) {
            $this->warn('Kunci VAPID sudah ada di .env. Membuat kunci baru akan memutus semua notifikasi yang sudah diaktifkan siswa.');

            if (! $this->confirm('Tetap buat kunci baru?', false)) {
                return self::SUCCESS;
            }
        }

        $kunci = VAPID::createVapidKeys();

        $this->line('Tempel tiga baris ini ke berkas .env (ganti email-nya dengan email lu):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$kunci['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$kunci['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:emaillu@example.com');
        $this->newLine();
        $this->comment('Kunci privat itu rahasia: jangan dibagikan dan jangan masuk ke tempat umum. Setelah menempel, jalankan: php artisan config:clear');

        return self::SUCCESS;
    }
}