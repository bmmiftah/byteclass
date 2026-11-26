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
use App\Http\Controllers\AnticurangController;
use App\Http\Controllers\CbtController;
use App\Http\Controllers\SoalController;
use App\Http\Controllers\UjianController;
use App\Http\Middleware\SiswaMasuk;

use	App\Http\Controllers\AdminGuruController;
use	App\Http\Controllers\GantiPasswordController;
use	App\Http\Controllers\PustakaController;
use	App\Http\Controllers\UndanganController;

Route::middleware(['auth', 'aktif', 'milik']) ->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    
    Route::get('/dashboard', [\App\Http\Controllers\BerandaGuruController::class, 'index'])->name('dashboard');

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
    Route::get('kelas/{kelas}/akun/kode-masuk', [AkunSiswaController::class, 'kodeMasuk'])->name('akun.kodemasuk');
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

    // ---- Fase 9E: anti-curang ----
    Route::post('ujian/{ujian}/anticurang', [AnticurangController::class, 'simpan'])->name('ujian.anticurang');
    Route::get('ujian/{ujian}/log/{pengerjaan}', [AnticurangController::class, 'log'])->name('ujian.log');
    Route::post('pengerjaan/{pengerjaan}/buka-kunci', [AnticurangController::class, 'bukaKunci'])->name('ujian.bukaKunci');
    Route::get('ujian/{ujian}/kemiripan', [AnticurangController::class, 'kemiripan'])->name('ujian.kemiripan');

    // Warna Kelas
    Route::post('kelas/{kelas}/warna', [KelasController::class, 'warna'])->name('kelas.warna');

    /* ===== Fase 8B: Tugas ===== */

    // Sisi guru (butuh login guru + penjaga isolasi 'milik')
    Route::middleware(['auth', 'aktif', 'milik'])->group(function () {
        Route::get('kelas/{kelas}/tugas', [\App\Http\Controllers\TugasController::class, 'index'])
            ->name('tugas.index');
        Route::get('kelas/{kelas}/tugas/baru', [\App\Http\Controllers\TugasController::class, 'create'])
            ->name('tugas.create');
        Route::post('kelas/{kelas}/tugas', [\App\Http\Controllers\TugasController::class, 'store'])
            ->name('tugas.store');
        Route::post('tugas/pengaturan', [\App\Http\Controllers\TugasController::class, 'pengaturan'])
            ->middleware('throttle:10,1')
            ->name('tugas.pengaturan');

        Route::get('tugas/{tugas}', [\App\Http\Controllers\TugasController::class, 'hasil'])
            ->name('tugas.hasil');
        Route::get('tugas/{tugas}/ubah', [\App\Http\Controllers\TugasController::class, 'edit'])
            ->name('tugas.edit');
        Route::put('tugas/{tugas}', [\App\Http\Controllers\TugasController::class, 'update'])
            ->name('tugas.update');

        Route::post('tugas/{tugas}/terbit', [\App\Http\Controllers\TugasController::class, 'terbit'])
            ->name('tugas.terbit');
        Route::post('tugas/{tugas}/tarik', [\App\Http\Controllers\TugasController::class, 'tarik'])
            ->name('tugas.tarik');
        Route::post('tugas/{tugas}/arsip', [\App\Http\Controllers\TugasController::class, 'arsip'])
            ->name('tugas.arsip');
        Route::post('tugas/{tugas}/buka-arsip', [\App\Http\Controllers\TugasController::class, 'bukaArsip'])
            ->name('tugas.bukaArsip');
        Route::post('tugas/{tugas}/tunda', [\App\Http\Controllers\TugasController::class, 'tunda'])
            ->name('tugas.tunda');

        Route::get('tugas/{tugas}/zip', [\App\Http\Controllers\TugasController::class, 'zip'])
            ->name('tugas.zip');
        Route::post('tugas/{tugas}/bersihkan', [\App\Http\Controllers\TugasController::class, 'bersihkan'])
            ->middleware('throttle:10,1')
            ->name('tugas.bersihkan');
        Route::delete('tugas/{tugas}', [\App\Http\Controllers\TugasController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('tugas.destroy');

        Route::post('tugas/{tugas}/kirim-nilai', [\App\Http\Controllers\TugasController::class, 'kirimNilai'])
            ->name('tugas.kirimNilai');
        Route::post('tugas/{tugas}/tandai/{siswa}', [\App\Http\Controllers\TugasController::class, 'tandai'])
            ->name('tugas.tandai');
        // ---- Fase 9D: rubrik untuk tugas ----
        Route::post('tugas/{tugas}/rubrik', [\App\Http\Controllers\RubrikTugasController::class, 'pasang'])->name('tugas.rubrik.pasang');
        Route::post('tugas/{tugas}/rubrik/lepas', [\App\Http\Controllers\RubrikTugasController::class, 'lepas'])->name('tugas.rubrik.lepas');
        Route::post('tugas-kumpul/{pengumpulan}/nilai', [\App\Http\Controllers\TugasController::class, 'nilai'])
            ->name('tugas.nilai');
        Route::get('tugas-berkas/{berkas}', [\App\Http\Controllers\TugasController::class, 'berkas'])
            ->name('tugas.berkas');
    });

    // ---- Fase 9A: leger nilai gabungan ----
    Route::get('kelas/{kelas}/leger', [\App\Http\Controllers\LegerController::class, 'index'])
        ->name('leger.index');
    Route::post('kelas/{kelas}/leger', [\App\Http\Controllers\LegerController::class, 'simpan'])
        ->name('leger.simpan');
    Route::get('kelas/{kelas}/leger/ekspor', [\App\Http\Controllers\LegerController::class, 'ekspor'])
        ->name('leger.ekspor');
    Route::get('kelas/{kelas}/leger/cetak', [\App\Http\Controllers\LegerController::class, 'cetak'])
        ->name('leger.cetak');
        
    // ---- Fase 9B: analisis ujian dan remedial ----
    Route::get('ujian/{ujian}/analisis', [\App\Http\Controllers\AnalisisController::class, 'ujian'])
        ->name('analisis.ujian');
    Route::post('ujian/{ujian}/remedial', [\App\Http\Controllers\AnalisisController::class, 'buatRemedial'])
        ->name('analisis.remedial');
    Route::post('ujian/{ujian}/remedial/terapkan', [\App\Http\Controllers\AnalisisController::class, 'terapkan'])
        ->name('analisis.terapkan');

    // ---- Fase 9A-2: rekap per bab ----
    Route::get('kelas/{kelas}/leger/bab', [\App\Http\Controllers\LegerBabController::class, 'index'])->name('leger.bab');
    Route::get('kelas/{kelas}/leger/bab/ekspor', [\App\Http\Controllers\LegerBabController::class, 'ekspor'])->name('leger.bab.ekspor');
    Route::get('kelas/{kelas}/leger/bab/cetak', [\App\Http\Controllers\LegerBabController::class, 'cetak'])->name('leger.bab.cetak');
    Route::get('kelas/{kelas}/leger/struktur', [\App\Http\Controllers\LegerBabController::class, 'struktur'])->name('leger.struktur');
    Route::post('kelas/{kelas}/leger/mode', [\App\Http\Controllers\LegerBabController::class, 'mode'])->name('leger.mode');
    Route::post('kelas/{kelas}/leger/ptspas', [\App\Http\Controllers\LegerBabController::class, 'ptsPas'])->name('leger.ptspas');
    Route::post('kelas/{kelas}/leger/bab', [\App\Http\Controllers\LegerBabController::class, 'babTambah'])->name('leger.bab.tambah');
    Route::post('leger/bab/{bab}', [\App\Http\Controllers\LegerBabController::class, 'babSimpan'])->name('leger.bab.simpan');
    Route::post('leger/bab/{bab}/hapus', [\App\Http\Controllers\LegerBabController::class, 'babHapus'])->name('leger.bab.hapus');
    Route::post('leger/bab/{bab}/salin', [\App\Http\Controllers\LegerBabController::class, 'babSalin'])->name('leger.bab.salin');

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
            Route::post('{ujian}/kejadian', [CbtController::class, 'kejadian'])->name('cbt.kejadian');
            Route::get('{ujian}/status', [CbtController::class, 'status'])->name('cbt.status');
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

/* ===== Fase 8A: Dashboard Saya ===== */
Route::prefix('saya')->group(function () {
    Route::get('masuk', [\App\Http\Controllers\SayaController::class, 'masukForm'])->name('saya.masuk');
    Route::post('masuk', [\App\Http\Controllers\SayaController::class, 'masuk'])
        ->middleware('throttle:60,1')
        ->name('saya.proses');

    Route::middleware(\App\Http\Middleware\SiswaMasuk::class)->group(function () {
        Route::get('/', [\App\Http\Controllers\SayaController::class, 'beranda'])->name('saya.beranda');
        Route::post('keluar', [\App\Http\Controllers\SayaController::class, 'keluar'])->name('saya.keluar');
    });
});

/* ===== Fase 8B: Tugas ===== */
// Sisi siswa (di bawah /saya, butuh masuk dengan kode pribadi)
Route::prefix('saya')->middleware(\App\Http\Middleware\SiswaMasuk::class)->group(function () {
    Route::get('tugas', [\App\Http\Controllers\SayaTugasController::class, 'index'])
        ->name('saya.tugas');
    Route::get('tugas/{tugas}', [\App\Http\Controllers\SayaTugasController::class, 'show'])
        ->whereNumber('tugas')
        ->name('saya.tugas.show');
    Route::post('tugas/{tugas}', [\App\Http\Controllers\SayaTugasController::class, 'kumpul'])
        ->whereNumber('tugas')
        ->middleware('throttle:30,1')
        ->name('saya.tugas.kumpul');
    Route::get('tugas/{tugas}/berkas/{berkas}', [\App\Http\Controllers\SayaTugasController::class, 'berkas'])
        ->whereNumber(['tugas', 'berkas'])
        ->name('saya.tugas.berkas');
});

/* ===== Fase 8C: Pengumuman, Agenda, Kalender ===== */

// Sisi guru
Route::middleware(['auth', 'aktif', 'milik'])->group(function () {
    Route::get('kelas/{kelas}/pengumuman', [\App\Http\Controllers\PengumumanController::class, 'index'])
        ->name('pengumuman.index');
    Route::get('kelas/{kelas}/pengumuman/baru', [\App\Http\Controllers\PengumumanController::class, 'create'])
        ->name('pengumuman.create');
    Route::post('kelas/{kelas}/pengumuman', [\App\Http\Controllers\PengumumanController::class, 'store'])
        ->name('pengumuman.store');
    Route::get('pengumuman/{pengumuman}/ubah', [\App\Http\Controllers\PengumumanController::class, 'edit'])
        ->name('pengumuman.edit');
    Route::put('pengumuman/{pengumuman}', [\App\Http\Controllers\PengumumanController::class, 'update'])
        ->name('pengumuman.update');
    Route::delete('pengumuman/{pengumuman}', [\App\Http\Controllers\PengumumanController::class, 'destroy'])
        ->name('pengumuman.destroy');

    Route::get('kelas/{kelas}/agenda', [\App\Http\Controllers\AgendaController::class, 'index'])
        ->name('agenda.index');
    Route::get('kelas/{kelas}/agenda/baru', [\App\Http\Controllers\AgendaController::class, 'create'])
        ->name('agenda.create');
    Route::post('kelas/{kelas}/agenda', [\App\Http\Controllers\AgendaController::class, 'store'])
        ->name('agenda.store');
    Route::get('agenda/{agenda}/ubah', [\App\Http\Controllers\AgendaController::class, 'edit'])
        ->name('agenda.edit');
    Route::put('agenda/{agenda}', [\App\Http\Controllers\AgendaController::class, 'update'])
        ->name('agenda.update');
    Route::delete('agenda/{agenda}', [\App\Http\Controllers\AgendaController::class, 'destroy'])
        ->name('agenda.destroy');
});

// Sisi siswa (di bawah /saya)
Route::prefix('saya')->middleware(\App\Http\Middleware\SiswaMasuk::class)->group(function () {
    Route::get('kalender', [\App\Http\Controllers\SayaKalenderController::class, 'kalender'])
        ->name('saya.kalender');
    Route::get('agenda', [\App\Http\Controllers\SayaKalenderController::class, 'agenda'])
        ->name('saya.agenda');
    Route::get('pengumuman', [\App\Http\Controllers\SayaKalenderController::class, 'pengumuman'])
        ->name('saya.pengumuman');
});

/* ===== Fase 8D: PWA, langganan kalender (.ics), pengingat ===== */
Route::get('manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])
    ->name('pwa.manifest');

// Tautan rahasia untuk aplikasi kalender (tanpa login; yang menjaga adalah token di alamatnya)
Route::get('kal/{token}.ics', [\App\Http\Controllers\KalenderIcsController::class, 'feed'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:120,1')
    ->name('kalender.ics');

Route::prefix('saya')->group(function () {
    Route::get('sw.js', [\App\Http\Controllers\PwaController::class, 'sw'])->name('pwa.sw');
    Route::get('offline', [\App\Http\Controllers\PwaController::class, 'offline'])->name('pwa.offline');

    Route::middleware(\App\Http\Middleware\SiswaMasuk::class)->group(function () {
        Route::post('kalender/langganan', [\App\Http\Controllers\KalenderIcsController::class, 'aktifkan'])
            ->name('saya.kalender.langganan');
        Route::post('kalender/langganan/ulang', [\App\Http\Controllers\KalenderIcsController::class, 'ulang'])
            ->name('saya.kalender.langganan.ulang');
        Route::delete('kalender/langganan', [\App\Http\Controllers\KalenderIcsController::class, 'matikan'])
            ->name('saya.kalender.langganan.mati');

        Route::post('push', [\App\Http\Controllers\SayaPushController::class, 'simpan'])
            ->name('saya.push.simpan');
        Route::post('push/hapus', [\App\Http\Controllers\SayaPushController::class, 'hapus'])
            ->name('saya.push.hapus');
        Route::post('push/uji', [\App\Http\Controllers\SayaPushController::class, 'uji'])
            ->middleware('throttle:6,1')
            ->name('saya.push.uji');
    });
});

    /* ===== Fase 9F: Gamifikasi (poin, lencana, papan tim, target kelas) ===== */
    // Sisi guru (login guru + penjaga isolasi 'milik')
    Route::middleware(['auth', 'aktif', 'milik'])->group(function () {
        Route::get('kelas/{kelas}/poin', [\App\Http\Controllers\PoinController::class, 'index'])->name('poin.index');
        Route::post('kelas/{kelas}/poin/aktif', [\App\Http\Controllers\PoinController::class, 'aktif'])->name('poin.aktif');
        Route::post('kelas/{kelas}/poin/pengaturan', [\App\Http\Controllers\PoinController::class, 'pengaturan'])->name('poin.pengaturan');
        Route::post('kelas/{kelas}/poin/mulai-baru', [\App\Http\Controllers\PoinController::class, 'mulaiBaru'])->name('poin.mulaiBaru');
        Route::post('kelas/{kelas}/poin/beri', [\App\Http\Controllers\PoinController::class, 'beri'])->middleware('throttle:30,1')->name('poin.beri');
        Route::delete('poin/{poin}', [\App\Http\Controllers\PoinController::class, 'hapus'])->whereNumber('poin')->name('poin.hapus');
    });

    // Sisi siswa (di bawah /saya)
    Route::prefix('saya')->middleware(\App\Http\Middleware\SiswaMasuk::class)->group(function () {
        Route::get('poin', [\App\Http\Controllers\SayaPoinController::class, 'index'])->name('saya.poin');
    });



require __DIR__ . '/auth.php';