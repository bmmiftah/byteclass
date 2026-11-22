<?php

namespace App\Console\Commands;

use App\Models\Tugas;
use App\Services\PenyimpananTugas;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Membersihkan file tugas yang sudah lewat jadwal "hapus otomatis".
 * Hanya menyentuh tugas berstatus arsip yang hapus_pada-nya sudah lewat.
 * Catatan (nilai, komentar, waktu) tidak pernah dihapus di sini.
 * Dijalankan harian lewat jadwal Laravel (lihat routes/console.php).
 */
class BersihkanTugas extends Command
{
    protected $signature = 'tugas:bersihkan {--kering : hanya tampilkan, tidak menghapus}';
    protected $description = 'Hapus file tugas yang sudah lewat jadwal hapus otomatis';

    public function handle(): int
    {
        $daftar = Tugas::where('status', 'arsip')
            ->whereNotNull('hapus_pada')
            ->where('hapus_pada', '<=', now())
            ->whereNull('file_dibersihkan_pada')
            ->get();

        if ($daftar->isEmpty()) {
            $this->info('Tidak ada tugas yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        foreach ($daftar as $t) {
            if ($this->option('kering')) {
                $this->line("[kering] {$t->id} {$t->judul}");

                continue;
            }

            [$jumlah, $byte] = PenyimpananTugas::bersihkan($t);
            $this->line("Dibersihkan: #{$t->id} {$t->judul} ($jumlah file, " . Number::fileSize($byte, 1) . ')');
        }

        return self::SUCCESS;
    }
}