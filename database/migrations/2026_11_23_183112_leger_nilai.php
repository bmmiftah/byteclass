<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kategori nilai yang dipilih guru di halaman Leger; null = ditebak otomatis (tugas, ujian, atau praktik)
        Schema::table('penilaians', function (Blueprint $table) {
            $table->string('kategori', 10)->nullable();
        });

        // Pengaturan leger per kelas dan mapel (mapel_id 0 = penilaian tanpa mapel)
        Schema::create('leger_pengaturans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->unsignedBigInteger('mapel_id')->default(0);
            $table->unsignedTinyInteger('kkm')->default(75);
            $table->string('kosong', 6)->default('lewati'); // lewati | nol
            $table->json('bobot');
            $table->timestamps();
            $table->unique(['kelas_id', 'mapel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leger_pengaturans');

        Schema::table('penilaians', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};