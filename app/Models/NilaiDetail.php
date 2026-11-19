<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NilaiDetail extends Model
{
    protected $fillable = [
        'nilai_siswa_id',
        'kriteria_id',
        'nilai',
    ];
}