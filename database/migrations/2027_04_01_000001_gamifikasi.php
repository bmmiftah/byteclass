<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan gamifikasi, satu baris per kelas. Kelas tanpa baris = gamifikasi mati.
        Schema::create('gamifikasi_pengaturans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->unique()->constrained('kelas')->cascadeOnDelete();
            $table->boolean('aktif')->default(false);
            $table->boolean('papan_individu')->default(false); // 5 besar per siswa, mati kecuali dinyalakan
            $table->date('mulai_pada'); // aktivitas sebelum tanggal ini tidak dihitung
            $table->foreignId('kelompok_sesi_id')->nullable()->constrained('kelompok_sesis')->nullOnDelete();
            $table->date('tim_mulai')->nullable(); // awal "putaran" papan tim
            $table->unsignedInteger('target_poin')->nullable(); // target bersama satu kelas
            $table->string('target_judul', 80)->nullable(); // hadiahnya, mis. "Nonton film bareng"
            $table->date('target_mulai')->nullable(); // awal hitungan target
            $table->timestamps();
        });

        // Buku besar poin: satu baris = satu kejadian yang memberi (atau mengurangi) poin.
        Schema::create('poin_siswas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->string('jenis', 16); // tugas_tepat | tugas_telat | ujian | hadir | pekan | manual
            $table->string('sumber_tipe', 16)->nullable(); // tugas | ujian | presensi | pekan (kosong untuk manual)
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->smallInteger('poin');
            $table->string('keterangan', 150)->nullable();
            $table->foreignId('guru_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('terjadi_pada');
            $table->timestamps();

            // Satu kejadian sumber hanya boleh memberi poin satu kali (baris manual tidak terkena, karena sumber_id kosong)
            $table->unique(['siswa_id', 'jenis', 'sumber_tipe', 'sumber_id'], 'poin_sumber_unik');
            $table->index(['kelas_id', 'terjadi_pada']);
        });

        Schema::create('lencana_siswas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->string('kode', 24);
            $table->dateTime('diraih_pada');
            $table->unique(['siswa_id', 'kode'], 'lencana_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lencana_siswas');
        Schema::dropIfExists('poin_siswas');
        Schema::dropIfExists('gamifikasi_pengaturans');
    }
};