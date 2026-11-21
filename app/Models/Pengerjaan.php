<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class Pengerjaan extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'ujian.kelas';
    
    protected $fillable = [
        'ujian_id',
        'siswa_id',
        'mulai_pada',
        'batas_pada',
        'selesai_pada',
        'status',
        'urutan_soal',
        'urutan_opsi',
        'sesi_kode',
        'pelanggaran',
        'skor',
        'nilai',
        'perlu_koreksi',
    ];

    protected $casts = [
        'mulai_pada' => 'datetime',
        'batas_pada' => 'datetime',
        'selesai_pada' => 'datetime',
        'urutan_soal' => 'array',
        'urutan_opsi' => 'array',
        'perlu_koreksi' => 'boolean',
        'skor' => 'float',
        'nilai' => 'float',
    ];

    public function ujian()
    {
        return $this->belongsTo(Ujian::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function jawabans()
    {
        return $this->hasMany(Jawaban::class);
    }

    public function sisaDetik(): int
    {
        return max(0, $this->batas_pada->getTimestamp() - time());
    }
}