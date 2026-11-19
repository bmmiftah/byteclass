<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DenahMeja extends Model
{
    protected $fillable = [
        'denah_id',
        'x',
        'y',
        'terkunci',
        'siswa_id',
    ];

    protected $casts = [
        'terkunci' => 'boolean',
    ];

    public function denah()
    {
        return $this->belongsTo(Denah::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}