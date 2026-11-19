<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MateriBab extends Model
{
    protected $fillable = ['kelas_id', 'mapel_id', 'judul', 'deskripsi', 'urutan', 'status', 'terbit_pada'];

    protected $casts = ['terbit_pada' => 'datetime'];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function items()
    {
        return $this->hasMany(MateriItem::class, 'bab_id')->orderBy('urutan')->orderBy('id');
    }

    // Bab yang boleh dilihat siswa: terbit dan jadwalnya sudah tiba
    public function scopeTerlihat(Builder $q): Builder
    {
        return $q->where('status', 'terbit')
            ->where(function ($w) {
                $w->whereNull('terbit_pada')->orWhere('terbit_pada', '<=', now());
            });
    }

    public function bisaDilihat(): bool
    {
        return $this->status === 'terbit' && (! $this->terbit_pada || $this->terbit_pada->lte(now()));
    }

    public function labelStatus(): string
    {
        if ($this->status !== 'terbit') {
            return 'Draft';
        }

        return $this->bisaDilihat() ? 'Terbit' : 'Dijadwalkan';
    }

    // Tampilkan stiker "Baru" selama 7 hari sejak terbit
    public function baru(): bool
    {
        return ($this->terbit_pada ?? $this->created_at)->gt(now()->subDays(7));
    }
}