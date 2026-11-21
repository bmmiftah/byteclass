<?php

use App\Http\Controllers\KelasController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiswaController;
use Illuminate\Support\Facades\Route;
use	App\Http\Controllers\DenahController;
use	App\Http\Controllers\KelompokController;
use App\Http\Controllers\MapelController;
use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\RubrikController;

use App\Http\Controllers\MateriController;
use App\Http\Controllers\SiswaMateriController;

use App\Http\Controllers\AlatController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatatanController;
use App\Http\Controllers\JurnalController;
use App\Http\Controllers\PresensiController;

use App\Http\Controllers\AkunSiswaController;
use App\Http\Controllers\CbtController;
use App\Http\Controllers\SoalController;
use App\Http\Controllers\UjianController;
use App\Http\Middleware\SiswaMasuk;

use	App\Http\Controllers\AdminGuruController;
use	App\Http\Controllers\GantiPasswordController;
use	App\Http\Controllers\PustakaController;
use	App\Http\Controllers\UndanganController;

Route::middleware(['auth', 'aktif', 'milik']) ->group(function () {
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

    // Penilaian dan Rubrik
    Route::get('mapel', [MapelController::class, 'index'])->name('mapel.index');
    Route::post('mapel', [MapelController::class, 'store'])->name('mapel.store');
    Route::delete('mapel/{mapel}', [MapelController::class, 'destroy'])->name('mapel.destroy');

    Route::get('rubrik', [RubrikController::class, 'index'])->name('rubrik.index');
    Route::post('rubrik', [RubrikController::class, 'store'])->name('rubrik.store');
    Route::get('rubrik/{rubrik}/edit', [RubrikController::class, 'edit'])->name('rubrik.edit');
    Route::put('rubrik/{rubrik}', [RubrikController::class, 'update'])->name('rubrik.update');
    Route::post('rubrik/{rubrik}/salin', [RubrikController::class, 'salin'])->name('rubrik.salin');
    Route::delete('rubrik/{rubrik}', [RubrikController::class, 'destroy'])->name('rubrik.destroy');

    Route::get('kelas/{kelas}/penilaian', [PenilaianController::class, 'index'])->name('penilaian.index');
    Route::post('kelas/{kelas}/penilaian', [PenilaianController::class, 'store'])->name('penilaian.store');
    Route::get('kelas/{kelas}/rekap', [PenilaianController::class, 'rekapKelas'])->name('penilaian.rekapKelas');
    Route::get('kelas/{kelas}/rekap/ekspor', [PenilaianController::class, 'eksporKelas'])->name('penilaian.eksporKelas');

    Route::get('penilaian/{penilaian}', [PenilaianController::class, 'show'])->name('penilaian.show');
    Route::get('penilaian/{penilaian}/nilai', [PenilaianController::class, 'nilai'])->name('penilaian.nilai');
    Route::put('penilaian/{penilaian}/nilai', [PenilaianController::class, 'simpan'])->name('penilaian.simpan');
    Route::get('penilaian/{penilaian}/ekspor', [PenilaianController::class, 'ekspor'])->name('penilaian.ekspor');
    Route::delete('penilaian/{penilaian}', [PenilaianController::class, 'destroy'])->name('penilaian.destroy');

    // Materi
    Route::get('kelas/{kelas}/materi', [MateriController::class, 'index'])->name('materi.index');
    Route::post('kelas/{kelas}/materi', [MateriController::class, 'store'])->name('materi.store');
    Route::post('kelas/{kelas}/materi/kode-baru', [MateriController::class, 'kodeBaru'])->name('materi.kodeBaru');

    Route::get('materi/{bab}', [MateriController::class, 'edit'])->name('materi.edit');
    Route::put('materi/{bab}', [MateriController::class, 'update'])->name('materi.update');
    Route::delete('materi/{bab}', [MateriController::class, 'destroy'])->name('materi.destroy');
    Route::post('materi/{bab}/pindah', [MateriController::class, 'pindahBab'])->name('materi.pindahBab');
    Route::post('materi/{bab}/bagikan', [MateriController::class, 'bagikan'])->name('materi.bagikan');
    Route::post('materi/{bab}/salin', [MateriController::class, 'salin'])->name('materi.salin');

    Route::post('materi/{bab}/item', [MateriController::class, 'itemStore'])->name('materi.item.store');
    Route::delete('materi-item/{item}', [MateriController::class, 'itemDestroy'])->name('materi.item.destroy');
    Route::post('materi-item/{item}/pindah', [MateriController::class, 'itemPindah'])->name('materi.item.pindah');

    Route::get('materi-item/{item}/edit', [MateriController::class, 'itemEdit'])->name('materi.item.edit');
    Route::put('materi-item/{item}', [MateriController::class, 'itemUpdate'])->name('materi.item.update');
    Route::post('materi-gambar', [MateriController::class, 'gambarUnggah'])->name('materi.gambar');
    Route::get('gbr/{nama}', [SiswaMateriController::class, 'gambar'])
    ->where('nama', '[A-Za-z0-9]{32}\.(jpg|png|webp|gif)')->name('gambar.lihat');

    // Pelengkap Harian
    Route::get('kelas/{kelas}/presensi', [PresensiController::class, 'index'])->name('presensi.index');
    Route::post('kelas/{kelas}/presensi', [PresensiController::class, 'simpan'])->name('presensi.simpan');
    Route::get('kelas/{kelas}/presensi/rekap', [PresensiController::class, 'rekap'])->name('presensi.rekap');

    Route::get('kelas/{kelas}/alat', [AlatController::class, 'index'])->name('alat.index');

    Route::get('kelas/{kelas}/catatan', [CatatanController::class, 'index'])->name('catatan.index');
    Route::post('kelas/{kelas}/catatan', [CatatanController::class, 'store'])->name('catatan.store');
    Route::delete('catatan/{catatan}', [CatatanController::class, 'destroy'])->name('catatan.destroy');

    Route::resource('jurnal', JurnalController::class)->except(['show', 'create']);

    Route::post('pustaka/rubrik/{rubrik}/bagikan', [PustakaController::class, 'rubrikBagikan'])
    ->name('pustaka.rubrik.bagikan');

    Route::post('pustaka/template', [PustakaController::class, 'templateSimpan'])
        ->name('pustaka.template.simpan');

    Route::post('pustaka/template/{template}/bagikan', [PustakaController::class, 'templateBagikan'])
        ->name('pustaka.template.bagikan');

    Route::delete('pustaka/template/{template}', [PustakaController::class, 'templateHapus'])
        ->name('pustaka.template.hapus');

    Route::get('kelas-arsip', [KelasController::class, 'arsip'])->name('kelas.arsip');
    Route::post('kelas/{kelas}/arsipkan', [KelasController::class, 'arsipkan'])->name('kelas.arsipkan');

    // ================== CBT ================== //
    Route::get('kelas/{kelas}/akun', [AkunSiswaController::class, 'index'])->name('akun.index');
    Route::post('kelas/{kelas}/akun', [AkunSiswaController::class, 'buat'])->name('akun.buat');
    Route::get('kelas/{kelas}/akun/kartu', [AkunSiswaController::class, 'kartu'])->name('akun.kartu');
    Route::post('siswa/{siswa}/akun/reset', [AkunSiswaController::class, 'reset'])->name('akun.reset');

    Route::resource('soal', SoalController::class)->except(['show']);
    Route::post('soal/{soal}/salin', [SoalController::class, 'salin'])->name('soal.salin');
    Route::get('soal/{soal}/gambar', [SoalController::class, 'gambar'])->name('soal.gambar');

    Route::get('kelas/{kelas}/ujian', [UjianController::class, 'index'])->name('ujian.index');
    Route::post('kelas/{kelas}/ujian', [UjianController::class, 'store'])->name('ujian.store');
    Route::get('ujian/{ujian}', [UjianController::class, 'edit'])->name('ujian.edit');
    Route::put('ujian/{ujian}', [UjianController::class, 'update'])->name('ujian.update');
    Route::delete('ujian/{ujian}', [UjianController::class, 'destroy'])->name('ujian.destroy');
    Route::post('ujian/{ujian}/status', [UjianController::class, 'status'])->name('ujian.status');
    Route::post('ujian/{ujian}/token', [UjianController::class, 'tokenBaru'])->name('ujian.token');
    Route::get('ujian/{ujian}/pantau', [UjianController::class, 'pantau'])->name('ujian.pantau');
    Route::get('ujian/{ujian}/pantau.json', [UjianController::class, 'pantauJson'])->name('ujian.pantauJson');
    Route::post('pengerjaan/{pengerjaan}/aksi', [UjianController::class, 'aksi'])->name('ujian.aksi');
    Route::get('ujian/{ujian}/hasil', [UjianController::class, 'hasil'])->name('ujian.hasil');
    Route::get('ujian/{ujian}/koreksi/{pengerjaan}', [UjianController::class, 'koreksi'])->name('ujian.koreksi');
    Route::post('ujian/{ujian}/koreksi/{pengerjaan}', [UjianController::class, 'simpanKoreksi'])
        ->name('ujian.simpanKoreksi');
    Route::post('ujian/{ujian}/kirim-nilai', [UjianController::class, 'kirimNilai'])->name('ujian.kirimNilai');

});

// ============== Route Siswa ==================== //
Route::middleware('throttle:240,1')->group(function () {
    Route::get('/', [SiswaMateriController::class, 'beranda'])->name('siswa.beranda');
    Route::get('belajar', fn (\Illuminate\Http\Request $r) => redirect()->route('siswa.beranda', $r->query()));
    Route::get('m/{kode}', [SiswaMateriController::class, 'kelas'])->name('siswa.kelas');
    Route::get('m/{kode}/cari', [SiswaMateriController::class, 'cari'])->name('siswa.cari');
    Route::get('m/{kode}/bab/{bab}', [SiswaMateriController::class, 'bab'])->name('siswa.bab');
    Route::get('m/{kode}/materi/{item}', [SiswaMateriController::class, 'item'])->name('siswa.item');
    Route::get('m/{kode}/berkas/{item}', [SiswaMateriController::class, 'berkas'])->name('siswa.berkas');
});

// ========== CBT ========== //
    Route::prefix('cbt')->group(function () {
        Route::get('/', [CbtController::class, 'masukForm'])->name('cbt.masuk');
        Route::post('/', [CbtController::class, 'masuk'])
            ->middleware('throttle:120,1')
            ->name('cbt.proses');

        Route::middleware(SiswaMasuk::class)->group(function () {
            Route::post('keluar', [CbtController::class, 'keluar'])->name('cbt.keluar');
            Route::get('daftar', [CbtController::class, 'daftar'])->name('cbt.daftar');
            Route::get('{ujian}/mulai', [CbtController::class, 'tokenForm'])->name('cbt.token');
            Route::post('{ujian}/mulai', [CbtController::class, 'mulai'])
                ->middleware('throttle:120,1')
                ->name('cbt.mulai');
            Route::get('{ujian}/kerjakan', [CbtController::class, 'kerjakan'])->name('cbt.kerjakan');
            Route::post('{ujian}/jawab', [CbtController::class, 'jawab'])->name('cbt.jawab');
            Route::post('{ujian}/pelanggaran', [CbtController::class, 'pelanggaran'])->name('cbt.pelanggaran');
            Route::post('{ujian}/selesai', [CbtController::class, 'selesai'])->name('cbt.selesai');
            Route::get('{ujian}/hasil', [CbtController::class, 'hasil'])->name('cbt.hasil');
            Route::get('{ujian}/gambar/{soal}', [CbtController::class, 'gambar'])->name('cbt.gambar');
        });
    });


Route::get('d/{token}',	[DenahController::class,	'publik'])->name('denah.publik');

Route::get('k/{token}',	[KelompokController::class,	'publik'])->name('kelompok.publik');

/* ===== Fase 7: multi-guru ===== */

// Pendaftaran lewat undangan (publik, dibatasi lajunya)
Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('undangan/{token}', [UndanganController::class, 'form'])->name('undangan.form');
    Route::post('undangan/{token}', [UndanganController::class, 'daftar'])->name('undangan.daftar');
});

// Ganti password wajib (setelah direset admin): hanya 'auth', tanpa 'aktif' agar tidak berputar
Route::middleware('auth')->group(function () {
    Route::get('ganti-password', [GantiPasswordController::class, 'form'])->name('password.wajib');
    Route::post('ganti-password', [GantiPasswordController::class, 'simpan'])->name('password.wajib.simpan');
});

// Pustaka: melihat dan memakai template guru lain (tanpa 'milik', pengecekan manual di controller)
Route::middleware(['auth', 'aktif'])->group(function () {
    Route::get('pustaka', [PustakaController::class, 'index'])->name('pustaka.index');

    Route::post('pustaka/rubrik/{rubrik}/ambil', [PustakaController::class, 'rubrikAmbil'])
        ->name('pustaka.rubrik.ambil');

    Route::post('pustaka/template/{template}/pakai', [PustakaController::class, 'templatePakai'])
        ->name('pustaka.template.pakai');
});

// Khusus admin: kelola guru + cadangan
Route::middleware(['auth', 'aktif', 'admin'])->group(function () {
    Route::get('admin/guru', [AdminGuruController::class, 'index'])->name('admin.guru.index');
    Route::post('admin/guru/undang', [AdminGuruController::class, 'undang'])->name('admin.guru.undang');

    Route::delete('admin/undangan/{undangan}', [AdminGuruController::class, 'batalUndangan'])
        ->name('admin.undangan.batal');

    Route::post('admin/guru/{user}/status', [AdminGuruController::class, 'ubahStatus'])
        ->name('admin.guru.status');

    Route::post('admin/guru/{user}/peran', [AdminGuruController::class, 'ubahPeran'])
        ->name('admin.guru.peran');

    Route::post('admin/guru/{user}/sandi', [AdminGuruController::class, 'resetPassword'])
        ->name('admin.guru.sandi');

    Route::get('backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('backup', [BackupController::class, 'buat'])->name('backup.buat');
    Route::get('backup/{nama}', [BackupController::class, 'unduh'])->name('backup.unduh');
    Route::delete('backup/{nama}', [BackupController::class, 'hapus'])->name('backup.hapus');
});

require __DIR__ . '/auth.php';