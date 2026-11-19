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
        Schema::create('materi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bab_id')->constrained('materi_babs')->cascadeOnDelete();
            $table->string('tipe', 10); // file | video | link | teks
            $table->string('judul');
            $table->string('path')->nullable(); // lokasi file di storage
            $table->string('nama_asli')->nullable(); // nama file asli untuk unduhan
            $table->string('ekstensi', 10)->nullable();
            $table->unsignedBigInteger('ukuran')->nullable(); // byte
            $table->string('url', 500)->nullable();
            $table->longText('isi')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materi_items');
    }
};
