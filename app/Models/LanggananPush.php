<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanggananPush extends Model
{
    protected $table = 'langganan_pushes';

    protected $fillable = [
        'akun_siswa_id',
        'endpoint_hash',
        'endpoint',
        'p256dh',
        'auth',
        'agen',
        'terakhir_kirim',
    ];

    protected $casts = [
        'terakhir_kirim' => 'datetime',
    ];

    public function akun()
    {
        return $this->belongsTo(AkunSiswa::class, 'akun_siswa_id');
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}