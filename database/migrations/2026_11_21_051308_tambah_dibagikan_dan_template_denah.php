<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubriks', function (Blueprint $table) {
            $table->boolean('dibagikan')->default(false);
        });

        Schema::create('denah_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama', 60);
            $table->unsignedTinyInteger('lebar');
            $table->unsignedTinyInteger('tinggi');
            $table->json('meja'); // Daftar posisi meja: [[x, y], ...]
            $table->boolean('dibagikan')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('denah_templates');

        Schema::table('rubriks', function (Blueprint $table) {
            $table->dropColumn('dibagikan');
        });
    }
};