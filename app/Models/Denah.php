<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Denah extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kelas_id',
        'nama',
        'lebar',
        'tinggi',
        'nama_lengkap',
    ];

    protected $casts = [
        'nama_lengkap' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Denah $denah) {
            $denah->token = $denah->token ?: Str::lower(Str::random(12));
        });
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mejas()
    {
        return $this->hasMany(DenahMeja::class);
    }

    public function aturan()
    {
        return $this->hasMany(DenahAturan::class);
    }

    public function riwayat()
    {
        return $this->hasMany(DenahRiwayat::class);
    }
}