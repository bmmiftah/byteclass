<?php

namespace App\Console\Commands;

use App\Models\MateriItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BersihkanGambarMateri extends Command
{
    protected $signature = 'materi:bersihkan-gambar {--hapus : Benar-benar menghapus (tanpa ini hanya menampilkan daftar)}';

    protected $description = 'Cari gambar teks materi yang tidak dipakai lagi (yatim) dan hapus bila diminta';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $yatim = [];

        foreach ($disk->files('materi-gambar') as $path) {
            $nama = basename($path);

            // beri tenggang 1 hari: gambar yang baru diunggah mungkin belum disimpan di materi
            if (now()->timestamp - $disk->lastModified($path) < 86400) {
                continue;
            }

            if (! MateriItem::where('isi', 'like', '%'.$nama.'%')->exists()) {
                $yatim[] = $path;
            }
        }

        if (! $yatim) {
            $this->info('Tidak ada gambar yatim.');

            return self::SUCCESS;
        }

        $ukuran = array_sum(array_map(fn ($p) => $disk->size($p), $yatim));
        $this->line(count($yatim).' gambar yatim, '.round($ukuran / 1048576, 2).' MB.');

        if ($this->option('hapus')) {
            $disk->delete($yatim);
            $this->info('Sudah dihapus.');
        } else {
            $this->comment('Tambahkan --hapus untuk menghapusnya.');
        }

        return self::SUCCESS;
    }
}