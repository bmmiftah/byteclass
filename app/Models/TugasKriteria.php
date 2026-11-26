<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;

class TugasKriteria extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'tugas.kelas';
    protected $table = 'tugas_kriterias';

    protected $fillable = [
        'tugas_id',
        'nama',
        'tipe',
        'maks',
        'bobot',
        'urutan',
    ];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class);
    }
}