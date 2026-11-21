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
        Schema::create('jawabans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengerjaan_id')
                ->constrained('pengerjaans')
                ->cascadeOnDelete();
            $table->foreignId('soal_id')
                ->constrained('soals')
                ->cascadeOnDelete();
            $table->text('jawaban')->nullable();
            $table->boolean('ragu')->default(false);
            $table->boolean('benar')->nullable();
            $table->decimal('skor', 6, 2)->nullable();
            $table->timestamps();
            $table->unique(['pengerjaan_id', 'soal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jawabans');
    }
};
