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
        'warna',
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
            $kelas->kode_publik = $kelas->kode_publik ?: static::kodeBaru();
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

    // Pilihan warna kelas: nama => kode warna (semuanya enak dibaca dengan teks hitam)
    public const WARNA = [
        'kuning' => '#FFD93D',
        'biru'   => '#4D96FF',
        'hijau'  => '#6BCB77',
        'oranye' => '#FF8C42',
        'pink'   => '#FF6B9D',
    ];

    public function getWarnaHexAttribute(): string
    {
        return self::WARNA[$this->warna] ?? self::WARNA['kuning'];
    }

    // Warna untuk kelas baru: bergantian mengikuti jumlah kelas guru
    public static function warnaBerikut(int $guruId): string
    {
        $nama = array_keys(self::WARNA);

        return $nama[static::withTrashed()->where('guru_id', $guruId)->count() % count($nama)];
    }

    // Kode kelas tanpa huruf/angka yang mudah tertukar (0 O 1 I L), huruf kecil
    public static function kodeBaru(): string
    {
        do {
            $kode = strtolower(AkunSiswa::acak(8));
        } while (static::withTrashed()->where('kode_publik', $kode)->exists());

        return $kode;
    }

    // "k7mr4xwp" -> "K7MR-4XWP" (untuk ditampilkan di kartu)
    public function getKodeTampilAttribute(): string
    {
        $k = strtoupper((string) $this->kode_publik);

        return strlen($k) === 8
            ? substr($k, 0, 4) . '-' . substr($k, 4)
            : $k;
    }

}