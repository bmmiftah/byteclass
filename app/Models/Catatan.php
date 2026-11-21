<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class Catatan extends Model
{
    use MilikGuru;
    
    protected string $pemilikVia = 'kelas';

    protected $fillable = [
        'kelas_id',
        'siswa_id',
        'tanggal',
        'jenis',
        'poin',
        'isi',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelas()
    {
        return$this->belongsTo(Kelas::class);
    }
}