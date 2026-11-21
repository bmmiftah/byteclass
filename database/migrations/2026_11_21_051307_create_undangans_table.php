<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('undangans', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->string('email')->nullable(); // Bila diisi, hanya email ini yang boleh mendaftar
            $table->string('peran', 10)->default('guru');
            $table->foreignId('dibuat_oleh')->constrained('users')->cascadeOnDelete();
            $table->dateTime('berlaku_sampai');
            $table->dateTime('dipakai_pada')->nullable();
            $table->foreignId('dipakai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('undangans');
    }
};