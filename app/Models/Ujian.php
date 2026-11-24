<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use	App\Models\Concerns\MilikGuru;

class Ujian extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'kelas';
    
    protected $fillable = [
        'guru_id',
        'kelas_id',
        'mapel_id',
        'penilaian_id',
        'judul',
        'durasi',
        'token',
        'buka_pada',
        'tutup_pada',
        'acak_soal',
        'acak_opsi',
        'tampil_nilai',
        'status',
        'remedial_dari_id',
    ];

    protected $casts = [
        'buka_pada' => 'datetime',
        'tutup_pada' => 'datetime',
        'acak_soal' => 'boolean',
        'acak_opsi' => 'boolean',
        'tampil_nilai' => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function penilaian()
    {
        return $this->belongsTo(Penilaian::class);
    }

    public function pengerjaans()
    {
        return $this->hasMany(Pengerjaan::class);
    }

    public function soals()
    {
        return $this->belongsToMany(Soal::class, 'ujian_soals')
            ->withPivot('urutan', 'bobot')
            ->orderBy('ujian_soals.urutan');
    }

    public static function tokenBaru(): string
    {
        return AkunSiswa::acak(6);
    }

    // Boleh dimulai siswa sekarang?
    public function bisaMulai(): bool
    {
        $n = now();

        return $this->status === 'buka'
            && (! $this->buka_pada || $this->buka_pada->lte($n))
            && (! $this->tutup_pada || $this->tutup_pada->gte($n));
    }

    public function labelStatus(): string
    {
        if ($this->status === 'draft') {
            return 'Draft';
        }

        if ($this->status === 'tutup') {
            return 'Ditutup';
        }

        return $this->bisaMulai() ? 'Dibuka' : 'Dijadwalkan';
    }

    // Urutan soal dan pilihan untuk satu siswa (diacak jika diminta)
    public function susunUrutan(): array
    {
        $soals = $this->soals()->get();
        $ids = $soals->pluck('id')->all();

        if ($this->acak_soal) {
            shuffle($ids);
        }

        $opsi = [];
        foreach ($soals as $s) {
            if ($s->tipe === 'pg') {
                $huruf = array_keys($s->opsi ?? []);

                if ($this->acak_opsi) {
                    shuffle($huruf);
                }

                $opsi[$s->id] = $huruf;
            }
        }

        return [$ids, $opsi];
    }

    /* ---------- Fase 9B: remedial dan peserta terbatas ---------- */

    public function asal()
    {
        return $this->belongsTo(Ujian::class, 'remedial_dari_id');
    }

    public function remedials()
    {
        return $this->hasMany(Ujian::class, 'remedial_dari_id')->orderBy('id');
    }

    public function pesertas()
    {
        return $this->belongsToMany(Siswa::class, 'ujian_pesertas', 'ujian_id', 'siswa_id');
    }

    public function isRemedial(): bool
    {
        return $this->remedial_dari_id !== null;
    }

    // Siswa ini boleh melihat dan mengerjakan? (tanpa daftar peserta = seluruh kelas)
    public function bisaDiikuti(int $siswaId): bool
    {
        return ! $this->pesertas()->exists() || $this->pesertas()->where('siswas.id', $siswaId)->exists();
    }

    // Ujian yang boleh dilihat siswa ini; null = tanpa filter (tampilan guru)
    public function scopeUntukSiswa($q, ?int $siswaId)
    {
        if ($siswaId === null) {
            return $q;
        }

        return $q->where(function ($w) use ($siswaId) {
            $w->whereDoesntHave('pesertas')
                ->orWhereHas('pesertas', fn ($p) => $p->where('siswas.id', $siswaId));
        });
    }

    // Siswa aktif yang menjadi sasaran ujian ini (peserta terbatas, atau seluruh kelas)
    public function siswaPeserta()
    {
        $q = $this->kelas->siswas()->where('aktif', true);

        if ($this->pesertas()->exists()) {
            $q->whereIn('id', $this->pesertas()->select('siswas.id'));
        }

        return $q;
    }

}