<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasBerkas extends Model
{
    use \App\Models\Concerns\MilikGuru;

    protected string $pemilikVia = 'pengumpulan.tugas.kelas';
    protected $table = 'tugas_berkas';

    protected $fillable = [
        'pengumpulan_id',
        'nama_asli',
        'path',
        'ukuran',
        'mime',
        'dibersihkan_pada',
    ];

    protected $casts = [
        'dibersihkan_pada' => 'datetime',
    ];

    public function pengumpulan()
    {
        return $this->belongsTo(TugasPengumpulan::class, 'pengumpulan_id');
    }

    public function getAdaAttribute(): bool
    {
        return $this->path !== null;
    }
}