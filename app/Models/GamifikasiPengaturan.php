<?php

namespace App\Models;

use App\Models\Concerns\MilikGuru;
use Illuminate\Database\Eloquent\Model;

class GamifikasiPengaturan extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'kelas';
    protected $table = 'gamifikasi_pengaturans';

    protected $fillable = [
        'kelas_id',
        'aktif',
        'papan_individu',
        'mulai_pada',
        'kelompok_sesi_id',
        'tim_mulai',
        'target_poin',
        'target_judul',
        'target_mulai',
    ];

    protected $casts = [
        'aktif'          => 'boolean',
        'papan_individu' => 'boolean',
        'mulai_pada'     => 'date',
        'tim_mulai'      => 'date',
        'target_mulai'   => 'date',
        'target_poin'    => 'integer',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /** Pengaturan kelas ini; kalau belum ada, objek baru yang BELUM disimpan (gamifikasi mati). */
    public static function untuk(int $kelasId): self
    {
        return static::where('kelas_id', $kelasId)->first()
            ?? new static([
                'kelas_id'       => $kelasId,
                'aktif'          => false,
                'papan_individu' => false,
                'mulai_pada'     => today(),
            ]);
    }

    /** Dipakai menu siswa di setiap halaman: satu query ringan. */
    public static function aktifUntuk(?int $kelasId): bool
    {
        return $kelasId && static::where('kelas_id', $kelasId)->where('aktif', true)->exists();
    }

    /** Awal hitungan papan tim: putaran tim, tetapi tidak pernah lebih awal dari awal gamifikasi. */
    public function awalTim(): string
    {
        return $this->palingAkhir($this->tim_mulai);
    }

    public function awalTarget(): string
    {
        return $this->palingAkhir($this->target_mulai);
    }

    private function palingAkhir($tanggal): string
    {
        $dasar = $this->mulai_pada->toDateString();

        return $tanggal && $tanggal->toDateString() > $dasar ? $tanggal->toDateString() : $dasar;
    }
}