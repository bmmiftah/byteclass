<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $t->foreignId('mapel_id')->nullable()->constrained('mapels')->nullOnDelete();
            $t->foreignId('penilaian_id')->nullable()->constrained('penilaians')->nullOnDelete();
            $t->string('judul', 150);
            $t->longText('instruksi')->nullable();
            $t->string('jenis', 10)->default('file');         // file | link | teks | eksternal
            $t->dateTime('tenggat');
            $t->string('telat', 6)->default('tutup');            // tutup | terima
            $t->boolean('boleh_ulang')->default(true);
            $t->unsignedTinyInteger('maks_berkas')->default(3);
            $t->unsignedTinyInteger('ukuran_mb')->default(10);
            $t->string('jenis_berkas', 10)->default('umum'); // umum | gambar | dokumen
            $t->string('tautan_eksternal', 500)->nullable();
            $t->string('kode_konfirmasi', 20)->nullable();
            $t->string('status', 8)->default('draf');          // draf | terbit | arsip
            $t->dateTime('terbit_pada')->nullable();
            $t->dateTime('diarsipkan_pada')->nullable();
            $t->dateTime('hapus_pada')->nullable();
            $t->dateTime('file_dibersihkan_pada')->nullable();
            $t->timestamps();

            $t->index(['kelas_id', 'status']);
        });

        Schema::create('tugas_pengumpulans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
            $t->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $t->longText('teks')->nullable();
            $t->string('tautan', 500)->nullable();
            $t->dateTime('dikirim_pada');
            $t->boolean('telat')->default(false);
            $t->boolean('ditandai_guru')->default(false); // Guru menandai "sudah kumpul" (mis. kertas)
            $t->dateTime('terverifikasi_pada')->nullable();
            $t->decimal('nilai', 5, 2)->nullable();
            $t->text('komentar')->nullable();
            $t->dateTime('dinilai_pada')->nullable();
            $t->timestamps();

            $t->unique(['tugas_id', 'siswa_id']);
        });

        Schema::create('tugas_berkas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengumpulan_id')->constrained('tugas_pengumpulans')->cascadeOnDelete();
            $t->string('nama_asli', 200);
            $t->string('path', 255)->nullable(); // null = file sudah dibersihkan
            $t->unsignedInteger('ukuran')->default(0);
            $t->string('mime', 100)->nullable();
            $t->dateTime('dibersihkan_pada')->nullable();
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            // Hapus file tugas otomatis N hari setelah diarsipkan; null = mati
            $t->unsignedSmallInteger('hapus_otomatis_hari')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('hapus_otomatis_hari');
        });

        Schema::dropIfExists('tugas_berkas');
        Schema::dropIfExists('tugas_pengumpulans');
        Schema::dropIfExists('tugas');
    }
};