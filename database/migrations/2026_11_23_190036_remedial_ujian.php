<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ujian remedial menunjuk ke ujian asalnya
        Schema::table('ujians', function (Blueprint $table) {
            $table->unsignedBigInteger('remedial_dari_id')->nullable()->index();
        });

        // Peserta terbatas: bila sebuah ujian punya baris di sini, hanya siswa itu yang boleh melihat dan mengerjakannya
        Schema::create('ujian_pesertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_id')->constrained('ujians')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->unique(['ujian_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_pesertas');

        Schema::table('ujians', function (Blueprint $table) {
            $table->dropColumn('remedial_dari_id');
        });
    }
};