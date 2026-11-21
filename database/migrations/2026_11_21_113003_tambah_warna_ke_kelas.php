<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $urutan = ['kuning', 'biru', 'hijau', 'oranye', 'pink'];

    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->string('warna', 10)->default('kuning');
        });

        // Kelas yang sudah ada diberi warna bergantian, supaya tidak kuning semua
        DB::table('kelas')->orderBy('id')->get()->each(function ($k, $i) {
            DB::table('kelas')
                ->where('id', $k->id)
                ->update(['warna' => $this->urutan[$i % count($this->urutan)]]);
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('warna');
        });
    }
};