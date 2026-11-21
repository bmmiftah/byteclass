<?php

namespace App\Console\Commands;

use App\Models\Concerns\MilikGuru;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RuteLaravel;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;

class CekIsolasi extends Command
{
    protected $signature = 'isolasi:cek';

    protected $description = 'Periksa bahwa setiap route guru yang menerima model sudah dilindungi isolasi antar guru';

    public function handle(): int
    {
        $gagal = [];
        $manual = [];
        $diperiksa = 0;

        foreach (Route::getRoutes() as $rute) {
            /** @var RuteLaravel $rute */
            $middleware = $rute->gatherMiddleware();
            $dilindungi = in_array('milik', $middleware, true);
            $perluLogin = in_array('auth', $middleware, true);

            if (! $perluLogin || ! $rute->getControllerClass()) {
                continue;
            }

            $kelas = $rute->getControllerClass();
            $aksi = $rute->getActionMethod();

            // Controller invokable: nama method yang dikembalikan sama dengan nama class
            if (! method_exists($kelas, $aksi)) {
                $aksi = '__invoke';
            }

            if (! method_exists($kelas, $aksi)) {
                continue;
            }

            $metode = new ReflectionMethod($kelas, $aksi);

            foreach ($metode->getParameters() as $param) {
                $tipe = $param->getType()?->getName();

                if (! $tipe || ! class_exists($tipe) || ! is_subclass_of($tipe, Model::class)) {
                    continue;
                }

                $nama = $rute->getName() ?? $rute->uri();

                if (! $dilindungi) {
                    if (! str_starts_with($nama, 'admin.')) {
                        $manual[$nama] = class_basename($tipe);
                    }
                    continue;
                }

                $diperiksa++;

                if (! in_array(MilikGuru::class, class_uses_recursive($tipe), true)) {
                    $gagal[$tipe][] = $nama;
                }
            }
        }

        $this->info("Route terlindungi yang diperiksa: {$diperiksa}");

        if ($manual) {
            $this->warn('Route login TANPA penjaga otomatis (pastikan controller-nya mengecek sendiri):');
            foreach ($manual as $nama => $model) {
                $this->line("  - {$nama} ({$model})");
            }
        }

        if ($gagal) {
            $this->error('Model berikut BELUM memakai trait MilikGuru, padahal dipakai di route guru:');
            foreach ($gagal as $model => $rutes) {
                $this->line('  - '.class_basename($model).'  (mis. '.$rutes[0].')');
            }

            return self::FAILURE;
        }

        $this->info('Semua model di route guru sudah dilindungi.');

        return self::SUCCESS;
    }
}