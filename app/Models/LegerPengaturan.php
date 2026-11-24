<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegerPengaturan extends Model
{
    use \App\Models\Concerns\MilikGuru;

    protected string $pemilikVia = 'kelas';

    protected $table = 'leger_pengaturans';

    protected $fillable = [
        'kelas_id',
        'mapel_id',
        'kkm',
        'kosong',
        'bobot',
        'mode',
        'pts_penilaian_id',
        'pas_penilaian_id',
        'bobot_nkd',
        'bobot_pts',
        'bobot_pas',
    ];

    protected $casts = ['bobot' => 'array'];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}