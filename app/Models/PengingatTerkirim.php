<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengingatTerkirim extends Model
{
    protected $table = 'pengingat_terkirims';

    public $timestamps = false;

    protected $fillable = [
        'akun_siswa_id',
        'kunci',
        'dikirim_pada',
    ];

    protected $casts = [
        'dikirim_pada' => 'datetime',
    ];
}