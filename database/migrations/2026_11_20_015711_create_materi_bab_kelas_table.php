<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materi_bab_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materi_bab_id')->constrained('materi_babs')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['materi_bab_id', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi_bab_kelas');
    }
};