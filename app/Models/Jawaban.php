<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jawaban extends Model
{
    protected $fillable = [
        'pengerjaan_id',
        'soal_id',
        'jawaban',
        'ragu',
        'benar',
        'skor',
    ];

    protected $casts = [
        'ragu' => 'boolean',
        'benar' => 'boolean',
        'skor' => 'float',
    ];

    public function soal()
    {
        return $this->belongsTo(Soal::class);
    }
}