<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DenahRiwayat extends Model
{
    protected $fillable = [
        'denah_id',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function denah()
    {
        return $this->belongsTo(Denah::class);
    }
}