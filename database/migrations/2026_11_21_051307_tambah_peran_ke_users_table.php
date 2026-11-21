<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('peran', 10)->default('guru'); // admin | guru
            $table->boolean('aktif')->default(true);
            $table->boolean('harus_ganti_password')->default(false);
            $table->dateTime('terakhir_masuk')->nullable();
        });

        // Akun pertama (milik lu sendiri) otomatis menjadi admin
        $pertama = DB::table('users')->min('id');
        if ($pertama) {
            DB::table('users')->where('id', $pertama)->update(['peran' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['peran', 'aktif', 'harus_ganti_password', 'terakhir_masuk']);
        });
    }
};