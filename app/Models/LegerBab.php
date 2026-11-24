<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;

class LegerBab extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'kelas';
    protected $table = 'leger_babs';

    protected $fillable = [
        'kelas_id',
        'mapel_id',
        'nama',
        'urutan',
        'pengetahuan',
        'keterampilan',
    ];

    protected $casts = [
        'pengetahuan'  => 'boolean',
        'keterampilan' => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function slots()
    {
        return $this->hasMany(LegerSlot::class, 'leger_bab_id')
            ->orderBy('urutan')
            ->orderBy('id');
    }
}