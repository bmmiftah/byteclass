<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KelompokAnggota extends Model
{
    protected $fillable = [
        'kelompok_id',
        'siswa_id',
        'terkunci',
    ];

    protected $casts = [
        'terkunci' => 'boolean',
    ];

    public function kelompok()
    {
        return $this->belongsTo(Kelompok::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}