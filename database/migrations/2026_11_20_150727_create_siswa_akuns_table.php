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
        Schema::create('siswa_akuns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')
                ->unique()
                ->constrained('siswas')
                ->cascadeOnDelete();
            $table->string('kode', 8)->unique();
            $table->timestamp('terakhir_masuk')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa_akuns');
    }
};
