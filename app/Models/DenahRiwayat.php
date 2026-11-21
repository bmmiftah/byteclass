<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class DenahRiwayat extends Model
{
    use MilikGuru;

    protected string $pemilikVila = 'denah.kelas';

    protected $fillable = [
        'denah_id',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function denah()
    {
        return $this->belongsTo(Denah::class);
    }
}