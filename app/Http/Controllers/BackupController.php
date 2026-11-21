<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BackupController extends Controller
{
    public function index()
    {
        $dir = storage_path('app/backup');
        $file = array_merge(
            glob($dir.'/backup-*.zip') ?: [],
            glob($dir.'/backup-*.sql') ?: []
        );

        $daftar = collect($file)
            ->map(fn ($p) => [
                'nama' => basename($p),
                'ukuran' => filesize($p),
                'waktu' => Carbon::createFromTimestamp(filemtime($p)),
            ])
            ->sortByDesc('nama')
            ->values();

        return view('backup.index', compact('daftar'));
    }

    public function buat(Request $request)
    {
        @set_time_limit(300);

        Artisan::call('backup:jalankan', [
            '--materi' => $request->boolean('materi'),
        ]);

        return back()->with('ok', 'Cadangan dibuat.');
    }

    public function unduh(string $nama)
    {
        return response()->download($this->path($nama));
    }

    public function hapus(string $nama)
    {
        unlink($this->path($nama));

        return back()->with('ok', 'Cadangan dihapus.');
    }

    // Nama file dibatasi pola ketat agar tidak bisa menyusup ke folder lain
    private function path(string $nama): string
    {
        abort_unless(preg_match('/^backup-\d{8}-\d{6}\.(zip|sql)$/', $nama), 404);

        $p = storage_path('app/backup/'.$nama);
        abort_unless(is_file($p), 404);

        return $p;
    }
}