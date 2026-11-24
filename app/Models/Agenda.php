<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    use \App\Models\Concerns\MilikGuru;

    protected string $pemilikVia = 'kelas';
    protected $table = 'agendas';

    protected $fillable = [
        'kelas_id',
        'judul',
        'keterangan',
        'jenis',
        'mulai',
        'selesai',
        'seharian',
    ];

    protected $casts = [
        'mulai'    => 'datetime',
        'selesai'  => 'datetime',
        'seharian' => 'boolean',
    ];

    public const JENIS = [
        'kegiatan' => 'Kegiatan',
        'libur'    => 'Libur',
        'penting'  => 'Penting',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /** Tanggal terakhir yang dicakup (acara beberapa hari memakai tanggal selesai) */
    public function getAkhirAttribute()
    {
        return $this->selesai && $this->selesai->gte($this->mulai) ? $this->selesai : $this->mulai;
    }
}