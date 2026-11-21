<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DenahTemplate extends Model
{
    use MilikGuru;

    protected $fillable = [
        'guru_id',
        'nama',
        'lebar',
        'tinggi',
        'meja',
        'dibagikan',
    ];

    protected $casts = [
        'meja' => 'array',
        'dibagikan' => 'boolean',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}