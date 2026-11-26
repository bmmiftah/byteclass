<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;

class TugasNilaiKriteria extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'pengumpulan.tugas.kelas';
    protected $table = 'tugas_nilai_kriterias';

    protected $fillable = [
        'pengumpulan_id',
        'tugas_kriteria_id',
        'nilai',
    ];

    public function pengumpulan()
    {
        return $this->belongsTo(TugasPengumpulan::class, 'pengumpulan_id');
    }

    public function kriteria()
    {
        return $this->belongsTo(TugasKriteria::class, 'tugas_kriteria_id');
    }
}