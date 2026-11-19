<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenilaianKriteria extends Model
{
    protected $fillable = [
        'penilaian_id',
        'nama',
        'tipe',
        'maks',
        'bobot',
        'urutan',
    ];
}