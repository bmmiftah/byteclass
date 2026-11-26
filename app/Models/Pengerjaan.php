<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengerjaan extends Model
{
    use \App\Models\Concerns\MilikGuru;
    protected string $pemilikVia = 'ujian.kelas';

    protected $fillable = [
        'ujian_id', 'siswa_id', 'mulai_pada', 'batas_pada', 'selesai_pada', 'status',
        'urutan_soal', 'urutan_opsi', 'sesi_kode', 'pelanggaran', 'skor', 'nilai', 'perlu_koreksi',
        'terkunci_pada', 'dasar_batas', 'kena_batas',
    ];

    protected $casts = [
        'mulai_pada'    => 'datetime',
        'batas_pada'    => 'datetime',
        'selesai_pada'  => 'datetime',
        'terkunci_pada' => 'datetime',
        'urutan_soal'   => 'array',
        'urutan_opsi'   => 'array',
        'perlu_koreksi' => 'boolean',
        'kena_batas'    => 'boolean',
        'skor'          => 'float',
        'nilai'         => 'float',
    ];

    public function ujian()    { return $this->belongsTo(Ujian::class); }
    public function siswa()    { return $this->belongsTo(Siswa::class); }
    public function jawabans() { return $this->hasMany(Jawaban::class); }

    // Fase 9E: catatan kejadian selama ujian
    public function kejadians() { return $this->hasMany(PengerjaanKejadian::class)->orderBy('terjadi_pada')->orderBy('id'); }

    public function terkunci(): bool
    {
        return $this->terkunci_pada !== null;
    }

    public function sisaDetik(): int
    {
        return max(0, $this->batas_pada->getTimestamp() - time());
    }
}
