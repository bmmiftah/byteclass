<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Undangan extends Model
{
    protected $table = 'undangans';

    protected $fillable = [
        'email',
        'peran',
        'dibuat_oleh',
        'berlaku_sampai',
    ];

    protected $casts = [
        'berlaku_sampai' => 'datetime',
        'dipakai_pada' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Undangan $u) {
            $u->token = $u->token ?: Str::random(40);
        });
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function masihBerlaku(): bool
    {
        return $this->dipakai_pada === null && $this->berlaku_sampai?->isFuture() === true;
    }
}