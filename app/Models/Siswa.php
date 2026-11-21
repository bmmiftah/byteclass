<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use	App\Models\Concerns\MilikGuru;

class Siswa extends Model
{
    use HasFactory, SoftDeletes, MilikGuru;

    protected string $pemilikVia = 'kelas';

    protected $fillable = [
        'kelas_id',
        'nis',
        'nama',
        'nama_panggilan',
        'gender',
        'aktif',
    ];

    // kelas
    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
    
    // catatan
    public function catatans()	
    {
        return $this->hasMany(Catatan::class);	
    }

    // akun siswa
    public function akun()
    {
        return $this->hasOne(AkunSiswa::class);
    }
}