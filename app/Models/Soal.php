<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class Soal extends Model
{
    use MilikGuru;
    
    protected $fillable = [
        'guru_id',
        'mapel_id',
        'tipe',
        'teks',
        'gambar',
        'opsi',
        'kunci',
        'bobot',
    ];

    protected $casts = [
        'opsi' => 'array',
    ];

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function ujians()
    {
        return $this->belongsToMany(Ujian::class, 'ujian_soals');
    }

    public function label(): string
    {
        return [
            'pg' => 'Pilihan ganda',
            'isian' => 'Isian singkat',
            'esai' => 'Uraian',
        ][$this->tipe] ?? $this->tipe;
    }

    // Sudah pernah dikerjakan siswa lewat ujian mana pun?
    public function sudahDikerjakan(): bool
    {
        return Pengerjaan::whereIn('ujian_id', $this->ujians()->pluck('ujians.id'))->exists();
    }
}