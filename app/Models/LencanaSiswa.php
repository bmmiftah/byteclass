<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LencanaSiswa extends Model
{
    public $timestamps = false;

    protected $table = 'lencana_siswas';

    protected $fillable = [
        'siswa_id',
        'kode',
        'diraih_pada',
    ];

    protected $casts = [
        'diraih_pada' => 'datetime',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}