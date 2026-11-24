<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    // Tanpa huruf/angka yang mudah tertukar: 0 O 1 I L
    public const HURUF = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $hidden = [
        'ingat_token',
        'kal_token',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function pushes()
    {
        return $this->hasMany(LanggananPush::class, 'akun_siswa_id');
    }

    /** Tautan langganan kalender (.ics) milik siswa ini, atau null kalau belum diaktifkan. */
    public function tautanKalender(): ?string
    {
        return $this->kal_token ? route('kalender.ics', $this->kal_token) : null;
    }

    /** Buat (atau buat ulang) token langganan kalender. Tautan lama langsung mati. */
    public function buatTokenKalender(): string
    {
        $token = Str::random(40);
        $this->forceFill(['kal_token' => $token])->save();

        return $token;
    }

    public static function acak(int $panjang): string
    {
        $s = '';
        for ($i = 0; $i < $panjang; $i++) {
            $s .= self::HURUF[random_int(0, strlen(self::HURUF) - 1)];
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
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $masuk));
    }

    public function getKodeTampilAttribute(): string
    {
        return substr($this->kode, 0, 4) . '-' . substr($this->kode, 4);
    }
}