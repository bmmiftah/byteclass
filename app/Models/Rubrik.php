<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rubrik extends Model
{
    protected $fillable = [
        'guru_id',
        'nama',
    ];

    public function kriterias()
    {
        return $this->hasMany(RubrikKriteria::class)->orderBy('urutan');
    }
}