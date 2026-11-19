<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penilaian extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'kelas_id',
        'mapel_id',
        'rubrik_id',
        'kelompok_sesi_id',
        'judul',
        'materi',
        'jenis',
        'tanggal',
        'kkm',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function kelompokSesi()
    {
        return $this->belongsTo(KelompokSesi::class);
    }

    public function kriterias()
    {
        return $this->hasMany(PenilaianKriteria::class)->orderBy('urutan');
    }

    public function nilais()
    {
        return $this->hasMany(NilaiSiswa::class);
    }
}