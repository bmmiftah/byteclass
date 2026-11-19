<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kelas_id',
        'nis',
        'nama',
        'nama_panggilan',
        'gender',
        'aktif',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}