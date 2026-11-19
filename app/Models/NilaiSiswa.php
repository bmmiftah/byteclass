<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NilaiSiswa extends Model
{
    protected $fillable = [
        'penilaian_id',
        'siswa_id',
        'total',
        'catatan',
    ];

    public function details()
    {
        return $this->hasMany(NilaiDetail::class, 'nilai_siswa_id');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function penilaian()
    {
        return $this->belongsTo(Penilaian::class);
    }
}