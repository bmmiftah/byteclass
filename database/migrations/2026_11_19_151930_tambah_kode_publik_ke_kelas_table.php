<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use	Illuminate\Support\Facades\DB;
use	Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->string('kode_publik', 12)->nullable()->unique()->after('semester');
        });

        // beri kode untuk kelas yang sudah ada
        DB::table('kelas')->whereNull('kode_publik')->get()->each(function ($k) {
            DB::table('kelas')->where('id', $k->id)->update(['kode_publik' => Str::lower(Str::random(8))]);
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropColumn('kode_publik');
        });
    }
};
