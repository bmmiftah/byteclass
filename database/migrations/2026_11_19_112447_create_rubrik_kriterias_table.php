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
        Schema::create('rubrik_kriterias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubrik_id')->constrained('rubriks')->cascadeOnDelete();

            $table->string('nama');
            $table->string('tipe', 10); // angka | poin | ceklis
            $table->unsignedSmallInteger('maks')->default(100);
            $table->unsignedSmallInteger('bobot')->default(1);
            $table->unsignedTinyInteger('urutan')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rubrik_kriterias');
    }
};
