<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pengerjaans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujians')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->dateTime('mulai_pada');
            $table->dateTime('batas_pada');
            $table->dateTime('selesai_pada')->nullable();
            $table->string('status', 10)->default('berjalan'); // berjalan | selesai
            $table->json('urutan_soal');
            $table->json('urutan_opsi')->nullable();
            $table->string('sesi_kode', 40)->nullable();
            $table->unsignedSmallInteger('pelanggaran')->default(0);
            $table->decimal('skor', 7, 2)->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->boolean('perlu_koreksi')->default(false);
            $table->timestamps();
            $table->unique(['ujian_id', 'siswa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengerjaans');
    }
};
