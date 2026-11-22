<?php

namespace App\Http\Controllers;

use App\Models\Tugas;
use App\Models\TugasBerkas;
use App\Models\TugasPengumpulan;
use App\Services\PenyimpananTugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sisi siswa untuk Tugas (di bawah /saya/tugas). Dijaga middleware SiswaMasuk.
 * Siswa hanya melihat tugas kelasnya sendiri dan hanya file miliknya sendiri.
 */
class SayaTugasController extends Controller
{
    private function akun(Request $request)
    {
        return $request->attributes->get('akun');
    }

    /** Tugas terlihat milik kelas siswa, atau 404 (tidak membocorkan keberadaan tugas kelas lain) */
    private function ambil(Request $request, int $id): Tugas
    {
        $siswa = $this->akun($request)->siswa;

        return Tugas::terlihat()
            ->where('kelas_id', $siswa->kelas_id)
            ->with('kelas')
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $siswa = $this->akun($request)->siswa;

        $semua = Tugas::terlihat()
            ->where('kelas_id', $siswa->kelas_id)
            ->orderBy('tenggat')
            ->get();

        $punya = TugasPengumpulan::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $semua->pluck('id'))
            ->get()
            ->keyBy('tugas_id');

        // Yang masih harus dikerjakan = belum dikumpulkan dan belum ditutup, urut dari tenggat terdekat
        $perlu = $semua->filter(fn ($t) => ! $punya->has($t->id) && $t->status === 'terbit' && $t->bolehKumpul())
            ->values();

        $lewat = $semua->filter(fn ($t) => ! $punya->has($t->id) && ! $perlu->contains('id', $t->id))
            ->sortByDesc('tenggat')
            ->values();

        $selesai = $semua->filter(fn ($t) => $punya->has($t->id))
            ->sortByDesc('tenggat')
            ->values();

        return view('saya.tugas', [
            'siswa'   => $siswa,
            'kelas'   => $siswa->kelas,
            'perlu'   => $perlu,
            'lewat'   => $lewat,
            'selesai' => $selesai,
            'punya'   => $punya,
        ]);
    }

    public function show(Request $request, int $tugas)
    {
        $siswa = $this->akun($request)->siswa;
        $t = $this->ambil($request, $tugas);
        $p = TugasPengumpulan::with('berkas')
            ->where('tugas_id', $t->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        return view('saya.tugas-detail', [
            'siswa'    => $siswa,
            'kelas'    => $siswa->kelas,
            'tugas'    => $t,
            'p'        => $p,
            'status'   => $t->statusUntuk($p),
            'saran'    => $t->jenis === 'eksternal' ? $t->namaBerkasSaran($siswa) : null,
            'bisaUbah' => $this->bisaKumpul($t, $p),
        ]);
    }

    /** Boleh mengumpulkan / mengumpulkan ulang sekarang? */
    private function bisaKumpul(Tugas $t, ?TugasPengumpulan $p): bool
    {
        if (! $t->bolehKumpul()) {
            return false;
        }

        if (! $p) {
            return true;
        }

        return $t->boleh_ulang && $p->nilai === null;
    }

    public function kumpul(Request $request, int $tugas)
    {
        $akun = $this->akun($request);
        $siswa = $akun->siswa;
        $t = $this->ambil($request, $tugas);
        $p = TugasPengumpulan::with('berkas')
            ->where('tugas_id', $t->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if (! $this->bisaKumpul($t, $p)) {
            return back()->withErrors([
                'umum' => $p
                    ? 'Pengumpulan ini sudah tidak bisa diubah.'
                    : 'Tugas ini sudah ditutup. Hubungi gurumu kalau perlu.',
            ]);
        }

        $isi = match ($t->jenis) {
            'file'      => $this->terimaFile($request, $t),
            'link'      => $request->validate(
                ['tautan' => 'required|url:http,https|max:500'],
                ['tautan.*' => 'Isi link yang benar, diawali http:// atau https://']
            ),
            'teks'      => $request->validate(
                ['teks' => 'required|string|min:2|max:5000'],
                ['teks.required' => 'Jawabanmu masih kosong.']
            ),
            'eksternal' => $this->terimaKonfirmasi($request, $t),
        };

        // File lama baru dihapus SETELAH file baru berhasil tersimpan, supaya kegagalan di tengah jalan
        // tidak membuat siswa kehilangan pengumpulan sebelumnya
        $lama = ($p && $t->jenis === 'file') ? $p->berkas->all() : [];
        $baru = [];

        try {
            DB::transaction(function () use ($t, $siswa, $p, $request, $isi, $lama, &$baru) {
                $data = [
                    'dikirim_pada'  => now(),
                    'telat'         => $t->lewat_tenggat,
                    'teks'          => $t->jenis === 'teks' ? $isi['teks'] : null,
                    'tautan'        => $t->jenis === 'link' ? $isi['tautan'] : null,
                    'ditandai_guru' => false,
                ];

                if ($p) {
                    $p->update($data);
                } else {
                    $p = TugasPengumpulan::create($data + [
                        'tugas_id' => $t->id,
                        'siswa_id' => $siswa->id,
                    ]);
                }

                if ($t->jenis === 'file') {
                    foreach ($request->file('berkas', []) as $f) {
                        $baru[] = PenyimpananTugas::simpan($f, $p);
                    }

                    if ($lama) {
                        TugasBerkas::whereIn('id', array_map(fn ($b) => $b->id, $lama))->delete();
                    }
                }
            });
        } catch (\Throwable $e) {
            PenyimpananTugas::hapusFile($baru); // Buang file baru yang sempat tersimpan; yang lama masih utuh
            throw $e;
        }

        PenyimpananTugas::hapusFile($lama);

        return redirect()
            ->route('saya.tugas.show', $t->id)
            ->with('ok', $t->jenis === 'eksternal'
                ? 'Konfirmasi tercatat. Gurumu akan memeriksa.'
                : 'Tugas terkirim!' . ($t->lewat_tenggat ? ' (tercatat telat)' : ''));
    }

    private function terimaFile(Request $request, Tugas $t): array
    {
        $ekstensi = implode(',', $t->ekstensiBoleh());

        $request->validate([
            'berkas'   => 'required|array|min:1|max:' . $t->maks_berkas,
            'berkas.*' => 'file|mimes:' . $ekstensi . '|max:' . ($t->ukuran_mb * 1024),
        ], [
            'berkas.required'   => 'Pilih minimal satu file.',
            'berkas.max'        => 'Maksimal ' . $t->maks_berkas . ' file.',
            'berkas.*.mimes'    => 'Jenis file tidak diizinkan. Yang boleh: ' . strtoupper($ekstensi) . '.',
            'berkas.*.max'      => 'Satu file maksimal ' . $t->ukuran_mb . ' MB.',
            'berkas.*.uploaded' => 'File gagal terunggah. Mungkin terlalu besar, coba file yang lebih kecil.',
        ]);

        return [];
    }

    private function terimaKonfirmasi(Request $request, Tugas $t): array
    {
        $request->validate(
            ['yakin' => 'accepted'],
            ['yakin.accepted' => 'Centang dulu bahwa kamu sudah mengumpulkan.']
        );

        if ($t->kode_konfirmasi) {
            $masuk = strtoupper(preg_replace('/\s+/', '', (string) $request->input('kode')));
            $benar = strtoupper(preg_replace('/\s+/', '', $t->kode_konfirmasi));

            if (! hash_equals($benar, $masuk)) {
                throw ValidationException::withMessages([
                    'kode' => 'Kode konfirmasi salah. Kode ada di nama folder pengumpulan.',
                ]);
            }
        }

        return [];
    }

    /** Siswa mengunduh kembali file miliknya sendiri */
    public function berkas(Request $request, int $tugas, int $berkas)
    {
        $siswa = $this->akun($request)->siswa;
        $t = $this->ambil($request, $tugas);

        $b = TugasBerkas::whereKey($berkas)
            ->whereIn(
                'pengumpulan_id',
                TugasPengumpulan::where('tugas_id', $t->id)
                    ->where('siswa_id', $siswa->id)
                    ->select('id')
            )
            ->firstOrFail();

        return PenyimpananTugas::kirimBerkas($b);
    }
}