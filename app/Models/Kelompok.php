<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kelompok extends Model
{
    protected $fillable = [
        'sesi_id',
        'nama',
        'urutan',
        'ketua_siswa_id',
    ];

    public function sesi()
    {
        return $this->belongsTo(KelompokSesi::class, 'sesi_id');
    }

    public function anggotas()
    {
        return $this->hasMany(KelompokAnggota::class);
    }
}