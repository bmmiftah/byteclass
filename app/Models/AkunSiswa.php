<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AkunSiswa extends Model
{
    protected $table = 'siswa_akuns';

    protected $fillable = [
        'siswa_id',
        'kode',
        'terakhir_masuk',
    ];

    protected $casts = [
        'terakhir_masuk' => 'datetime',
    ];

    // Tanpa huruf/angka yang mudah tertukar: 0, O, 1, I, L
    public const HURUF = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public static function acak(int $panjang): string
    {
        $s = '';
        $maks = strlen(self::HURUF) - 1;

        for ($i = 0; $i < $panjang; $i++) {
            $s .= self::HURUF[random_int(0, $maks)];
        }

        return $s;
    }

    public static function kodeBaru(): string
    {
        do {
            $kode = self::acak(8);
        } while (self::where('kode', $kode)->exists());

        return $kode;
    }

    // "bk7x-92qm " -> "BK7X92QM"
    public static function bersihkan(string $masuk): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $masuk));
    }

    public function getKodeTampilAttribute(): string
    {
        return substr($this->kode, 0, 4).'-'.substr($this->kode, 4);
    }
}