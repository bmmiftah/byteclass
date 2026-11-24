<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bab (materi/KD) per kelas dan mapel; mapel_id 0 = tanpa mapel
        Schema::create('leger_babs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->unsignedBigInteger('mapel_id')->default(0);
            $table->string('nama', 150);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('pengetahuan')->default(true);
            $table->boolean('keterampilan')->default(true);
            $table->timestamps();

            $table->index(['kelas_id', 'mapel_id']);
        });

        // Slot penilaian di dalam satu bab dan satu aspek; penilaian_id = penilaian yang mengisi slot ini
        Schema::create('leger_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leger_bab_id')->constrained('leger_babs')->cascadeOnDelete();
            $table->string('aspek', 12); // pengetahuan | keterampilan
            $table->string('nama', 80);
            $table->unsignedTinyInteger('bobot')->default(100);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unsignedBigInteger('penilaian_id')->nullable();
            $table->timestamps();
        });

        // Mode leger + PTS/PAS (di luar bab) + bobot gabungan untuk aspek Pengetahuan
        Schema::table('leger_pengaturans', function (Blueprint $table) {
            $table->string('mode', 8)->default('kategori'); // kategori (Fase 9A) | bab
            $table->unsignedBigInteger('pts_penilaian_id')->nullable();
            $table->unsignedBigInteger('pas_penilaian_id')->nullable();
            $table->unsignedTinyInteger('bobot_nkd')->default(50);
            $table->unsignedTinyInteger('bobot_pts')->default(25);
            $table->unsignedTinyInteger('bobot_pas')->default(25);
        });
    }

    public function down(): void
    {
        Schema::table('leger_pengaturans', function (Blueprint $table) {
            $table->dropColumn(['mode', 'pts_penilaian_id', 'pas_penilaian_id', 'bobot_nkd', 'bobot_pts', 'bobot_pas']);
        });

        Schema::dropIfExists('leger_slots');
        Schema::dropIfExists('leger_babs');
    }
};