<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengerjaanKejadian extends Model
{
    public $timestamps = false;

    protected $fillable = ['pengerjaan_id', 'jenis', 'detail', 'dihitung', 'terjadi_pada'];

    protected $casts = [
        'dihitung'     => 'boolean',
        'terjadi_pada' => 'datetime',
    ];

    public function pengerjaan() { return $this->belongsTo(Pengerjaan::class); }
}
