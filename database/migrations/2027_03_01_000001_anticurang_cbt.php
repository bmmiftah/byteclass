<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujians', function (Blueprint $table) {
            $table->boolean('blokir_salin')->default(true);
            $table->boolean('layar_penuh')->default(false);
            $table->unsignedSmallInteger('batas_pelanggaran')->nullable();   // kosong = tanpa batas
            $table->string('aksi_batas', 10)->default('kunci');              // kunci | kumpul
        });

        Schema::table('pengerjaans', function (Blueprint $table) {
            $table->dateTime('terkunci_pada')->nullable();
            $table->unsignedSmallInteger('dasar_batas')->default(0);          // pelanggaran saat terakhir dibuka kuncinya
            $table->boolean('kena_batas')->default(false);
        });

        Schema::create('pengerjaan_kejadians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengerjaan_id')->constrained('pengerjaans')->cascadeOnDelete();
            $table->string('jenis', 24);
            $table->string('detail', 120)->nullable();
            $table->boolean('dihitung')->default(false);
            $table->dateTime('terjadi_pada');

            $table->index(['pengerjaan_id', 'terjadi_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengerjaan_kejadians');

        Schema::table('pengerjaans', function (Blueprint $table) {
            $table->dropColumn(['terkunci_pada', 'dasar_batas', 'kena_batas']);
        });

        Schema::table('ujians', function (Blueprint $table) {
            $table->dropColumn(['blokir_salin', 'layar_penuh', 'batas_pelanggaran', 'aksi_batas']);
        });
    }
};
