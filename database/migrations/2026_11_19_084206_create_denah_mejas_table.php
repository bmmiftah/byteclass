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
        Schema::create('denah_mejas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('denah_id')->constrained('denahs')->cascadeOnDelete();
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->boolean('terkunci')->default(false);
            $table->foreignId('siswa_id')->nullable()->constrained('siswas')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('denah_mejas');
    }
};
