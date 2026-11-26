<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;

class PoinSiswa extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'kelas';
    protected $table = 'poin_siswas';

    protected $fillable = [
        'kelas_id',
        'siswa_id',
        'jenis',
        'sumber_tipe',
        'sumber_id',
        'poin',
        'keterangan',
        'guru_id',
        'terjadi_pada',
    ];

    protected $casts = [
        'poin'         => 'integer',
        'terjadi_pada' => 'date',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    public function manual(): bool
    {
        return $this->jenis === 'manual';
    }

    public function label(): string
    {
        return \App\Services\Gamifikasi::JENIS[$this->jenis] ?? $this->jenis;
    }
}