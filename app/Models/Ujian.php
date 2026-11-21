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
}