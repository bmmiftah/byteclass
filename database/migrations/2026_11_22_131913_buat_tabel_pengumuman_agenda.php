<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumumans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $t->string('judul', 150);
            $t->text('isi');
            $t->boolean('disematkan')->default(false);
            $t->dateTime('terbit_pada')->nullable();   // Null = langsung tayang
            $t->dateTime('berakhir_pada')->nullable(); // Null = tayang terus
            $t->timestamps();

            $t->index(['kelas_id', 'terbit_pada']);
        });

        Schema::create('agendas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $t->string('judul', 150);
            $t->text('keterangan')->nullable();
            $t->string('jenis', 10)->default('kegiatan'); // kegiatan | libur | penting
            $t->dateTime('mulai');
            $t->dateTime('selesai')->nullable();
            $t->boolean('seharian')->default(true);
            $t->timestamps();

            $t->index(['kelas_id', 'mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendas');
        Schema::dropIfExists('pengumumans');
    }
};