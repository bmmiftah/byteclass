<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class BackupJalankan extends Command
{
    protected $signature = 'backup:jalankan
        {--materi : Sertakan file materi (bisa besar)}
        {--simpan=7 : Jumlah cadangan terbaru yang disimpan}';

    protected $description = 'Cadangkan database (dan opsional file materi) ke storage/app/backup';

    public function handle(): int
    {
        @set_time_limit(300);

        $dir = storage_path('app/backup');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $stamp = now()->format('Ymd-His');
        $sql = $dir.'/backup-'.$stamp.'.sql';
        $hasil = $sql;

        $this->tulisSql($sql);

        if (class_exists(ZipArchive::class)) {
            $zipPath = $dir.'/backup-'.$stamp.'.zip';
            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                $zip->addFile($sql, 'database.sql');

                if ($this->option('materi')) {
                    $this->tambahFolder($zip, storage_path('app/private/materi'), 'materi');
                }

                $zip->close();
                unlink($sql);
                $hasil = $zipPath;
            }
        }

        // Simpan N cadangan terbaru saja
        $semua = array_merge(
            glob($dir.'/backup-*.zip') ?: [],
            glob($dir.'/backup-*.sql') ?: []
        );
        rsort($semua);

        foreach (array_slice($semua, max(1, (int) $this->option('simpan'))) as $lama) {
            @unlink($lama);
        }

        $this->info('Cadangan dibuat: '.basename($hasil));

        return self::SUCCESS;
    }

    private function tulisSql(string $path): void
    {
        $pdo = DB::connection()->getPdo();
        $h = fopen($path, 'w');

        fwrite(
            $h,
            "-- Cadangan ".now()->toDateTimeString()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n"
        );

        $tabels = array_map(
            fn ($r) => array_values((array) $r)[0],
            DB::select('SHOW TABLES')
        );

        $tanpaData = [
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
        ];

        foreach ($tabels as $t) {
            $buat = (array) DB::selectOne("SHOW CREATE TABLE `{$t}`");
            fwrite($h, "DROP TABLE IF EXISTS `{$t}`;\n".$buat['Create Table'].";\n\n");

            if (in_array($t, $tanpaData, true)) {
                continue;
            }

            $kumpulan = [];
            foreach (DB::table($t)->cursor() as $baris) {
                $nilai = array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    (array) $baris
                );
                $kumpulan[] = '('.implode(',', $nilai).')';

                if (count($kumpulan) >= 100) {
                    fwrite($h, "INSERT INTO `{$t}` VALUES ".implode(",\n", $kumpulan).";\n");
                    $kumpulan = [];
                }
            }

            if ($kumpulan) {
                fwrite($h, "INSERT INTO `{$t}` VALUES ".implode(",\n", $kumpulan).";\n");
            }

            fwrite($h, "\n");
        }

        fwrite($h, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($h);
    }

    private function tambahFolder(ZipArchive $zip, string $folder, string $awal): void
    {
        if (! is_dir($folder)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $berkas) {
            if ($berkas->isFile()) {
                $relatif = substr($berkas->getPathname(), strlen($folder) + 1);
                $zip->addFile(
                    $berkas->getPathname(),
                    $awal.'/'.str_replace('\\', '/', $relatif)
                );
            }
        }
    }
}