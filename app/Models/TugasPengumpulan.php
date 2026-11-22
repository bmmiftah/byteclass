<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TugasPengumpulan extends Model
{
    use \App\Models\Concerns\MilikGuru;

    protected string $pemilikVia = 'tugas.kelas';
    protected $table = 'tugas_pengumpulans';

    protected $fillable = [
        'tugas_id',
        'siswa_id',
        'teks',
        'tautan',
        'dikirim_pada',
        'telat',
        'ditandai_guru',
        'terverifikasi_pada',
        'nilai',
        'komentar',
        'dinilai_pada',
    ];

    protected $casts = [
        'dikirim_pada'       => 'datetime',
        'terverifikasi_pada' => 'datetime',
        'dinilai_pada'       => 'datetime',
        'telat'              => 'boolean',
        'ditandai_guru'      => 'boolean',
        'nilai'              => 'decimal:2',
    ];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    public function berkas()
    {
        return $this->hasMany(TugasBerkas::class, 'pengumpulan_id');
    }
}