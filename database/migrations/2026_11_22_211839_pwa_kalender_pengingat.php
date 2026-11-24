<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tautan rahasia langganan kalender (.ics) per siswa; null = belum diaktifkan
        Schema::table('siswa_akuns', function (Blueprint $table) {
            $table->string('kal_token', 64)->nullable()->unique();
        });

        // Satu baris = satu HP/browser siswa yang mengaktifkan notifikasi
        Schema::create('langganan_pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('akun_siswa_id')->constrained('siswa_akuns')->cascadeOnDelete();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('p256dh', 200);
            $table->string('auth', 100);
            $table->string('agen', 160)->nullable();
            $table->timestamp('terakhir_kirim')->nullable();
            $table->timestamps();
        });

        // Catatan pengingat yang sudah dikirim, supaya tidak dobel
        Schema::create('pengingat_terkirims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('akun_siswa_id')->constrained('siswa_akuns')->cascadeOnDelete();
            $table->string('kunci', 80);
            $table->timestamp('dikirim_pada');
            $table->unique(['akun_siswa_id', 'kunci']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengingat_terkirims');
        Schema::dropIfExists('langganan_pushes');

        Schema::table('siswa_akuns', function (Blueprint $table) {
            $table->dropColumn('kal_token');
        });
    }
};