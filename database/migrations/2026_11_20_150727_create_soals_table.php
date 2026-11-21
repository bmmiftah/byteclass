<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('soals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mapel_id')->nullable()->constrained('mapels')->nullOnDelete();
            $table->string('tipe', 10); // pg | isian | esai
            $table->text('teks');
            $table->string('gambar')->nullable();
            $table->json('opsi')->nullable(); // {"A":"...","B":"..."}
            $table->text('kunci')->nullable(); // pg: huruf | isian: a|b | esai: pedoman
            $table->unsignedTinyInteger('bobot')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('soals');
    }
};
