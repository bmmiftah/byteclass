<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class Jurnal extends Model
{
    use MilikGuru;
    
    protected $fillable = [
        'guru_id',
        'kelas_id',
        'mapel_id',
        'tanggal',
        'topik',
        'kegiatan',
        'kendala',
        'tindak_lanjut',
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
}