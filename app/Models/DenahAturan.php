<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DenahAturan extends Model
{
    protected $fillable = [
        'denah_id',
        'siswa_a',
        'siswa_b',
    ];

    public function denah()
    {
        return $this->belongsTo(Denah::class);
    }
}