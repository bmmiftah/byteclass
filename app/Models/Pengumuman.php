<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Pengumuman extends Model
{
    use \App\Models\Concerns\MilikGuru;

    protected string $pemilikVia = 'kelas';

    // Dieja eksplisit: pluralisasi bawaan Laravel akan menebak "pengumumen"
    protected $table = 'pengumumans';

    protected $fillable = [
        'kelas_id',
        'judul',
        'isi',
        'disematkan',
        'terbit_pada',
        'berakhir_pada',
    ];

    protected $casts = [
        'disematkan'    => 'boolean',
        'terbit_pada'   => 'datetime',
        'berakhir_pada' => 'datetime',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /** Sedang tayang: jadwal tayang sudah lewat dan belum berakhir */
    public function scopeTayang(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('terbit_pada')->orWhere('terbit_pada', '<=', now()))
            ->where(fn ($w) => $w->whereNull('berakhir_pada')->orWhere('berakhir_pada', '>=', now()));
    }

    /** Sematan di atas, lalu yang terbaru */
    public function scopeUrut(Builder $q): Builder
    {
        return $q->orderByDesc('disematkan')
            ->orderByRaw('coalesce(terbit_pada, created_at) desc')
            ->orderByDesc('id');
    }

    public function getWaktuAttribute()
    {
        return $this->terbit_pada ?? $this->created_at;
    }

    /** terjadwal | tayang | berakhir */
    public function getStatusAttribute(): string
    {
        if ($this->terbit_pada && $this->terbit_pada->isFuture()) {
            return 'terjadwal';
        }

        if ($this->berakhir_pada && $this->berakhir_pada->isPast()) {
            return 'berakhir';
        }

        return 'tayang';
    }

    public function getBaruAttribute(): bool
    {
        return $this->waktu && $this->waktu->gte(now()->subDays(3));
    }
}