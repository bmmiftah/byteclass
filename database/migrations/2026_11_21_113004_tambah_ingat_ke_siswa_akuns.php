<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa_akuns', function (Blueprint $table) {
            // Sidik jari (hash) token "ingat saya di HP ini"; null = tidak ada yang diingat
            $table->string('ingat_token', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('siswa_akuns', function (Blueprint $table) {
            $table->dropColumn('ingat_token');
        });
    }
};