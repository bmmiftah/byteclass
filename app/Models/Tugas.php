<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Tugas extends Model
{
    use \App\Models\Concerns\MilikGuru;

    protected string $pemilikVia = 'kelas';
    protected $table = 'tugas';

    protected $fillable = [
        'kelas_id',
        'mapel_id',
        'penilaian_id',
        'judul',
        'instruksi',
        'jenis',
        'tenggat',
        'telat',
        'boleh_ulang',
        'maks_berkas',
        'ukuran_mb',
        'jenis_berkas',
        'tautan_eksternal',
        'kode_konfirmasi',
        'status',
        'terbit_pada',
        'diarsipkan_pada',
        'hapus_pada',
        'file_dibersihkan_pada',
    ];

    protected $casts = [
        'tenggat'               => 'datetime',
        'terbit_pada'           => 'datetime',
        'diarsipkan_pada'       => 'datetime',
        'hapus_pada'            => 'datetime',
        'file_dibersihkan_pada' => 'datetime',
        'boleh_ulang'           => 'boolean',
    ];

    public const JENIS = [
        'file'      => 'Unggah file',
        'link'      => 'Tempel link',
        'teks'      => 'Jawaban teks',
        'eksternal' => 'Folder / link di luar (konfirmasi saja)',
    ];

    public const EKSTENSI = [
        'gambar'  => ['jpg', 'jpeg', 'png', 'webp'],
        'dokumen' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt'],
        'umum'    => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt'],
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

    public function pengumpulans()
    {
        return $this->hasMany(TugasPengumpulan::class);
    }

    public function kriterias()
    {
        return $this->hasMany(TugasKriteria::class)->orderBy('urutan')->orderBy('id');
    }

    public function ekstensiBoleh(): array
    {
        return self::EKSTENSI[$this->jenis_berkas] ?? self::EKSTENSI['umum'];
    }

    /** Terlihat oleh siswa: diterbitkan (atau sudah diarsipkan), dan jadwal tayangnya sudah lewat */
    public function scopeTerlihat(Builder $q): Builder
    {
        return $q->whereIn('status', ['terbit', 'arsip'])
            ->where(fn ($w) => $w->whereNull('terbit_pada')->orWhere('terbit_pada', '<=', now()));
    }

    public function getTerlihatAttribute(): bool
    {
        return in_array($this->status, ['terbit', 'arsip'], true)
            && (! $this->terbit_pada || $this->terbit_pada->lte(now()));
    }

    /** Sudah diterbitkan tapi jadwal tayangnya belum tiba (siswa belum melihatnya). */
    public function getTerjadwalAttribute(): bool
    {
        return $this->status === 'terbit' && $this->terbit_pada && $this->terbit_pada->gt(now());
    }

    /** Label & warna chip status untuk halaman guru. */
    public function labelStatus(): string
    {
        return $this->terjadwal
            ? 'Terjadwal'
            : ['draf' => 'Draf', 'terbit' => 'Terbit', 'arsip' => 'Arsip'][$this->status];
    }

    public function warnaStatus(): string
    {
        return $this->terjadwal
            ? 'var(--kuning)'
            : ['draf' => '#fff', 'terbit' => 'var(--hijau)', 'arsip' => '#ddd'][$this->status];
    }

    public function getLewatTenggatAttribute(): bool
    {
        return $this->tenggat->lt(now());
    }

    /** Boleh mengumpulkan sekarang? (terbit, belum arsip, dan belum lewat tenggat atau telat diterima) */
    public function bolehKumpul(): bool
    {
        return $this->status === 'terbit'
            && $this->terlihat
            && (! $this->lewat_tenggat || $this->telat === 'terima');
    }

    /**
     * Status tampilan untuk satu siswa: [kode, label, warna]
     * kode: dinilai | dikumpulkan | telat | ditutup | segera | belum
     */
    public function statusUntuk(?TugasPengumpulan $p): array
    {
        if ($p && $p->nilai !== null) {
            $nilaiFormat = rtrim(rtrim(number_format((float) $p->nilai, 2, ',', ''), '0'), ',');

            return ['dinilai', 'Nilai ' . $nilaiFormat, 'var(--hijau)'];
        }

        if ($p) {
            return ['dikumpulkan', $p->telat ? 'Dikumpulkan (telat)' : 'Sudah dikumpulkan', 'var(--biru)'];
        }

        if ($this->lewat_tenggat) {
            return $this->telat === 'terima' && $this->status === 'terbit'
                ? ['telat', 'Telat, masih diterima', 'var(--oranye)']
                : ['ditutup', 'Ditutup', 'var(--merah)'];
        }

        if ($this->tenggat->lt(now()->addDay())) {
            return ['segera', 'Segera berakhir', 'var(--oranye)'];
        }

        return ['belum', 'Belum dikumpulkan', 'var(--kuning)'];
    }

    public function tenggatTampil(): string
    {
        return $this->tenggat->copy()->locale('id')->translatedFormat('l, j F Y \\p\\u\\k\\u\\l H.i');
    }

    /** "3 hari lagi", "5 jam lagi", "lewat 2 jam" (pembulatan ke atas untuk sisa, ke bawah untuk lewat) */
    public function sisaWaktu(): string
    {
        $menit = (int) round(now()->diffInMinutes($this->tenggat, false));

        if ($menit >= 0) {
            return match (true) {
                $menit < 60   => max(1, $menit) . ' menit lagi',
                $menit < 1440 => (int) ceil($menit / 60) . ' jam lagi',
                default       => (int) ceil($menit / 1440) . ' hari lagi',
            };
        }

        $lewat = abs($menit);

        return 'lewat ' . match (true) {
            $lewat < 60   => max(1, $lewat) . ' menit',
            $lewat < 1440 => intdiv($lewat, 60) . ' jam',
            default       => intdiv($lewat, 1440) . ' hari',
        };
    }

    public function jumlahBerkasAda(): int
    {
        return TugasBerkas::whereNotNull('path')
            ->whereIn('pengumpulan_id', $this->pengumpulans()->select('id'))
            ->count();
    }

    public function ukuranBerkasAda(): int
    {
        return (int) TugasBerkas::whereNotNull('path')
            ->whereIn('pengumpulan_id', $this->pengumpulans()->select('id'))
            ->sum('ukuran');
    }

    /** Total byte file tugas milik seorang guru (hanya yang masih ada di disk) */
    public static function pemakaianGuru(int $guruId): int
    {
        return (int) DB::table('tugas_berkas')
            ->join('tugas_pengumpulans', 'tugas_pengumpulans.id', '=', 'tugas_berkas.pengumpulan_id')
            ->join('tugas', 'tugas.id', '=', 'tugas_pengumpulans.tugas_id')
            ->join('kelas', 'kelas.id', '=', 'tugas.kelas_id')
            ->where('kelas.guru_id', $guruId)
            ->whereNotNull('tugas_berkas.path')
            ->sum('tugas_berkas.ukuran');
    }

    /** Nama berkas yang disarankan untuk jenis "eksternal": 7A-Budi-Santoso-Tugas-Fotosintesis */
    public function namaBerkasSaran(Siswa $siswa): string
    {
        return Str::slug($this->kelas->nama . ' ' . $siswa->nama . ' ' . Str::limit($this->judul, 30, ''), '-');
    }
}