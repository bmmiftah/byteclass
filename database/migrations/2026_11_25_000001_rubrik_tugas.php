<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salinan (snapshot) kriteria rubrik yang dipasang ke satu tugas; mengubah rubrik di pustaka tidak mengubah tugas
        Schema::create('tugas_kriterias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
            $table->string('nama');
            $table->string('tipe', 10); // angka | poin | ceklis
            $table->unsignedSmallInteger('maks')->default(100);
            $table->unsignedSmallInteger('bobot')->default(1);
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();
        });

        // Nilai tiap kriteria untuk satu pengumpulan; totalnya disimpan di tugas_pengumpulans.nilai seperti biasa
        Schema::create('tugas_nilai_kriterias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengumpulan_id')->constrained('tugas_pengumpulans')->cascadeOnDelete();
            $table->foreignId('tugas_kriteria_id')->constrained('tugas_kriterias')->cascadeOnDelete();
            $table->decimal('nilai', 6, 2);
            $table->timestamps();

            $table->unique(['pengumpulan_id', 'tugas_kriteria_id'], 'tugas_nilai_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_nilai_kriterias');
        Schema::dropIfExists('tugas_kriterias');
    }
};