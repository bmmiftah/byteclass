<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KelompokSesi extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kelas_id',
        'nama',
        'nama_lengkap',
    ];

    protected $casts = [
        'nama_lengkap' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (KelompokSesi $sesi) {
            $sesi->token = $sesi->token ?: Str::lower(Str::random(12));
        });
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function kelompoks()
    {
        return $this->hasMany(Kelompok::class, 'sesi_id');
    }
}