<?php

use App\Http\Controllers\KelasController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiswaController;
use Illuminate\Support\Facades\Route;

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
});

require __DIR__ . '/auth.php';