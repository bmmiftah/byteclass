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
        Schema::create('penilaians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mapel_id')->nullable()->constrained('mapels')->nullOnDelete();
            $table->foreignId('rubrik_id')->nullable()->constrained('rubriks')->nullOnDelete();
            $table->foreignId('kelompok_sesi_id')->nullable()->constrained('kelompok_sesis')->nullOnDelete();

            $table->string('judul');
            $table->string('materi')->nullable();
            $table->string('jenis', 10)->default('individu'); // individu | kelompok
            $table->date('tanggal');
            $table->unsignedTinyInteger('kkm')->default(75);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penilaians');
    }
};
