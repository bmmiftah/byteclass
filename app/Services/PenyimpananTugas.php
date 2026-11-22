<?php

namespace App\Services;

use App\Models\Tugas;
use App\Models\TugasBerkas;
use App\Models\TugasPengumpulan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Semua urusan file tugas ada di sini: simpan, hapus, bersihkan, dan ZIP.
 * File disimpan di disk privat (storage/app/private), dengan nama acak, dan hanya bisa dibuka lewat controller.
 */
class PenyimpananTugas
{
    private static function disk()
    {
        return Storage::disk('local');
    }

    public static function simpan(UploadedFile $file, TugasPengumpulan $p): TugasBerkas
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        if (! in_array($ext, Tugas::EKSTENSI['umum'], true)) {
            $ext = 'bin'; // Jaga-jaga: tidak pernah menyimpan ekstensi yang bisa dijalankan server
        }

        $path = $file->storeAs('tugas/' . $p->tugas_id, Str::random(32) . '.' . $ext, 'local');

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            KompresGambar::proses(self::disk()->path($path));
        }

        try {
            return $p->berkas()->create([
                'nama_asli' => Str::limit(basename($file->getClientOriginalName()), 190, ''),
                'path'      => $path,
                'ukuran'    => (int) self::disk()->size($path),
                'mime'      => $file->getMimeType(),
            ]);
        } catch (\Throwable $e) {
            self::disk()->delete($path); // Jangan tinggalkan file yatim bila database gagal
            throw $e;
        }
    }

    /** Hapus file fisik dari daftar berkas (barisnya di database diurus pemanggil) */
    public static function hapusFile(iterable $berkas): void
    {
        foreach ($berkas as $b) {
            if ($b->path) {
                self::disk()->delete($b->path);
            }
        }
    }

    /** Kirim satu file sebagai unduhan (tidak pernah ditampilkan inline, supaya HTML/SVG tak bisa jalan) */
    public static function kirimBerkas(TugasBerkas $b)
    {
        abort_if(! $b->path || ! self::disk()->exists($b->path), 404, 'File sudah dibersihkan.');

        return response()->download(self::disk()->path($b->path), $b->nama_asli, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Total byte file materi + tugas di seluruh server (perkiraan, untuk angka kuota admin) */
    public static function totalByte(): int
    {
        return (int) TugasBerkas::whereNotNull('path')->sum('ukuran')
            + (int) DB::table('materi_items')->sum('ukuran');
    }

    /** Kuota hosting dalam byte (dari .env KUOTA_HOSTING_GB), atau null bila tidak diisi */
    public static function kuotaByte(): ?int
    {
        $gb = (float) config('tugas.kuota_gb');

        return $gb > 0 ? (int) ($gb * 1024 * 1024 * 1024) : null;
    }

    /** True bila server sudah terisi >= 85% dari kuota */
    public static function hampirPenuh(): bool
    {
        $kuota = self::kuotaByte();

        return $kuota !== null && self::totalByte() >= $kuota * 0.85;
    }

    /**
     * Hapus file fisik sebuah tugas, tetapi pertahankan catatannya (nilai, komentar, waktu, nama file asli).
     * Mengembalikan [jumlah file, total byte].
     */
    public static function bersihkan(Tugas $tugas): array
    {
        $jumlah = 0;
        $byte = 0;

        $daftar = TugasBerkas::whereNotNull('path')
            ->whereIn('pengumpulan_id', $tugas->pengumpulans()->select('id'))
            ->get();

        foreach ($daftar as $b) {
            self::disk()->delete($b->path);
            $jumlah++;
            $byte += $b->ukuran;
            $b->update(['path' => null, 'dibersihkan_pada' => now()]);
        }

        self::disk()->deleteDirectory('tugas/' . $tugas->id);
        $tugas->update(['file_dibersihkan_pada' => now(), 'hapus_pada' => null]);

        return [$jumlah, $byte];
    }

    /** Dipakai saat tugas dihapus seluruhnya */
    public static function hapusSemua(Tugas $tugas): void
    {
        self::disk()->deleteDirectory('tugas/' . $tugas->id);
    }

    /** Buat ZIP semua file tugas di folder sementara; mengembalikan path ZIP, atau null bila tidak ada file */
    public static function zip(Tugas $tugas): ?string
    {
        $pengumpulans = $tugas->pengumpulans()->with(['siswa', 'berkas'])->get()
            ->sortBy(fn ($p) => mb_strtolower($p->siswa->nama));

        $tmp = tempnam(sys_get_temp_dir(), 'tugas');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $ada = 0;
        $pakai = [];

        foreach ($pengumpulans as $p) {
            $n = 0;

            foreach ($p->berkas as $b) {
                if (! $b->path || ! self::disk()->exists($b->path)) {
                    continue;
                }

                $n++;
                $ext = pathinfo($b->path, PATHINFO_EXTENSION);
                $dasar = Str::slug($tugas->kelas->nama . ' ' . $p->siswa->nama, '-');
                $nama = $dasar . '-' . $n . '.' . $ext;

                for ($i = 2; isset($pakai[$nama]); $i++) {
                    $nama = $dasar . '-' . $i . '-' . $n . '.' . $ext;
                }

                $pakai[$nama] = true;
                $zip->addFile(self::disk()->path($b->path), $nama);
                $ada++;
            }
        }

        $zip->close();

        if ($ada === 0) {
            @unlink($tmp);

            return null;
        }

        return $tmp;
    }
}