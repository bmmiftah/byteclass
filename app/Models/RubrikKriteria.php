<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubrikKriteria extends Model
{
    protected $fillable = [
        'rubrik_id',
        'nama',
        'tipe',
        'maks',
        'bobot',
        'urutan',
    ];
}