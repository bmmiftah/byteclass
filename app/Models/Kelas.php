<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use	Illuminate\Support\Str;
use	App\Models\Concerns\MilikGuru;

class Kelas extends Model
{
    use HasFactory, SoftDeletes, MilikGuru;

    protected $table = 'kelas';

    protected $fillable = [
        'guru_id',
        'nama',
        'tahun_ajaran',
        'semester',
    ];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function siswas()
    {
        return $this->hasMany(Siswa::class);
    }

    public function denahs()
    {
        return $this->hasMany(Denah::class);
    }

    public function kelompokSesis()
    {
        return $this->hasMany(KelompokSesi::class);
    }

    public function penilaians()
    {
        return $this->hasMany(Penilaian::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Kelas $kelas) {
            $kelas->kode_publik = $kelas->kode_publik ?: Str::lower(Str::random(8));
        });
    }

    public function materiBabs()
    {
        return $this->hasMany(MateriBab::class)->orderBy('urutan')->orderBy('id');
    }

    // Semua bab yang tampil di kelas ini: milik sendiri + yang dibagikan dari kelas lain
    public function materiTampil()
    {
        return MateriBab::query()
            ->where(function ($q) {
                $q->where('kelas_id', $this->id)
                    ->orWhereHas('kelasLain', fn ($k) => $k->whereKey($this->id));
            })
            ->orderBy('urutan')
            ->orderBy('id');
    }

    // CBT
    public function ujians()
    {
        return $this->hasMany(Ujian::class)->latest();
    }

}