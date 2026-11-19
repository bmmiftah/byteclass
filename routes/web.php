<?php

use App\Http\Controllers\KelasController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiswaController;
use Illuminate\Support\Facades\Route;
use	App\Http\Controllers\DenahController;
use	App\Http\Controllers\KelompokController;

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('kelas.index'));
    Route::get('/dashboard', fn () => redirect()->route('kelas.index'))->name('dashboard');

    Route::resource('kelas', KelasController::class)
        ->only(['index', 'store', 'show', 'destroy'])
        ->parameters(['kelas' => 'kelas']);

    Route::post('kelas/{kelas}/siswa', [SiswaController::class, 'store'])->name('siswa.store');
    Route::post('kelas/{kelas}/siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
    Route::delete('siswa/{siswa}', [SiswaController::class, 'destroy'])->name('siswa.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Denah Tempat Duduk
    Route::get('kelas/{kelas}/denah', [DenahController::class, 'index'])->name('denah.index');
    Route::post('kelas/{kelas}/denah', [DenahController::class, 'store'])->name('denah.store');

    Route::get('denah/{denah}/edit', [DenahController::class, 'edit'])->name('denah.edit');
    Route::put('denah/{denah}/layout', [DenahController::class, 'layout'])->name('denah.layout');
    Route::post('denah/{denah}/acak', [DenahController::class, 'acak'])->name('denah.acak');
    Route::delete('denah/{denah}', [DenahController::class, 'destroy'])->name('denah.destroy');

    Route::post('denah/{denah}/aturan', [DenahController::class, 'aturanTambah'])->name('denah.aturan.store');
    Route::delete('denah-aturan/{aturan}', [DenahController::class, 'aturanHapus'])->name('denah.aturan.destroy');
    Route::post('denah-riwayat/{riwayat}/pulihkan', [DenahController::class, 'pulihkan'])->name('denah.pulihkan');

    // Bagi Kelompok
    Route::get('kelas/{kelas}/kelompok', [KelompokController::class, 'index'])->name('kelompok.index');
    Route::post('kelas/{kelas}/kelompok', [KelompokController::class, 'store'])->name('kelompok.store');

    Route::get('kelompok/{sesi}/edit', [KelompokController::class, 'edit'])->name('kelompok.edit');
    Route::put('kelompok/{sesi}/state', [KelompokController::class, 'state'])->name('kelompok.state');
    Route::post('kelompok/{sesi}/acak', [KelompokController::class, 'acak'])->name('kelompok.acak');
    Route::delete('kelompok/{sesi}', [KelompokController::class, 'destroy'])->name('kelompok.destroy');
});

Route::get('d/{token}',	[DenahController::class,	'publik'])->name('denah.publik');

Route::get('k/{token}',	[KelompokController::class,	'publik'])->name('kelompok.publik');

require __DIR__ . '/auth.php';