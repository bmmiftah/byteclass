<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;

class LegerSlot extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'bab.kelas';
    protected $table = 'leger_slots';

    protected $fillable = [
        'leger_bab_id',
        'aspek',
        'nama',
        'bobot',
        'urutan',
        'penilaian_id',
    ];

    public function bab()
    {
        return $this->belongsTo(LegerBab::class, 'leger_bab_id');
    }

    /** Penilaian yang mengisi slot ini (null kalau belum dipilih atau sudah dihapus). */
    public function penilaian()
    {
        return $this->belongsTo(Penilaian::class);
    }
}