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
        Schema::create('nilai_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nilai_siswa_id')->constrained('nilai_siswas')->cascadeOnDelete();
            $table->foreignId('kriteria_id')->constrained('penilaian_kriterias')->cascadeOnDelete();

            $table->decimal('nilai', 6, 2);

            $table->timestamps();

            $table->unique(['nilai_siswa_id', 'kriteria_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_details');
    }
};
