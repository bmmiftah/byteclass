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
        Schema::create('ujians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mapel_id')->nullable()->constrained('mapels')->nullOnDelete();
            $table->foreignId('penilaian_id')->nullable()->constrained('penilaians')->nullOnDelete();
            $table->string('judul');
            $table->unsignedSmallInteger('durasi')->default(60); // menit
            $table->string('token', 8);
            $table->dateTime('buka_pada')->nullable();
            $table->dateTime('tutup_pada')->nullable();
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_opsi')->default(true);
            $table->boolean('tampil_nilai')->default(false);
            $table->string('status', 10)->default('draft'); // draft | buka | tutup
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ujians');
    }
};
