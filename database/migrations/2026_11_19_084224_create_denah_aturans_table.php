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
        Schema::create('denah_aturans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('denah_id')->constrained('denahs')->cascadeOnDelete();
            $table->foreignId('siswa_a')->constrained('siswas')->cascadeOnDelete();
            $table->foreignId('siswa_b')->constrained('siswas')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('denah_aturans');
    }
};
