<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class DenahAturan extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'denah.kelas';

    protected $fillable = [
        'denah_id',
        'siswa_a',
        'siswa_b',
    ];

    public function denah()
    {
        return $this->belongsTo(Denah::class);
    }
}