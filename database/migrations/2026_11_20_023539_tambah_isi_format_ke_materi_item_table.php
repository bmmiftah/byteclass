<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materi_items', function (Blueprint $table) {
            // materi lama = md (Markdown); materi yang disimpan lewat editor baru = html
            $table->string('isi_format', 4)->default('md')->after('isi');
        });
    }

    public function down(): void
    {
        Schema::table('materi_items', function (Blueprint $table) {
            $table->dropColumn('isi_format');
        });
    }
};