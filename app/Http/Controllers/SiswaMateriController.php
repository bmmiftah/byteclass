<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\MateriBab;
use App\Models\MateriItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiswaMateriController extends Controller
{
    // Halaman awal: isi kode kelas
    public function beranda(Request $request)
    {
        $kode = strtolower(trim((string) $request->query('kode')));

        if ($kode === '') {
            return view('siswa.beranda', ['galat' => null]);
        }

        $kelas = Kelas::where('kode_publik', $kode)->first();

        if ($kelas) {
            return redirect()->route('siswa.kelas', $kelas->kode_publik);
        }

        return view('siswa.beranda', ['galat' => 'Kode kelas tidak ditemukan. Periksa lagi ya.']);
    }

    // Daftar bab satu kelas, dikelompokkan per mapel
    public function kelas(string $kode)
    {
        $kelas = $this->cariKelas($kode);
        $babs = $kelas->materiBabs()->terlihat()->with('mapel')->withCount('items')->get()
            ->groupBy(fn ($b) => $b->mapel?->nama ?? 'Umum');

        return view('siswa.kelas', compact('kelas', 'babs'));
    }

    // Isi satu bab
    public function bab(string $kode, MateriBab $bab)
    {
        $kelas = $this->cariKelas($kode);
        abort_unless($bab->kelas_id === $kelas->id && $bab->bisaDilihat(), 404);
        $bab->load('items', 'mapel');

        return view('siswa.bab', compact('kelas', 'bab'));
    }

    // Menyajikan file privat setelah dicek (bab harus terbit)
    public function berkas(Request $request, string $kode, MateriItem $item)
    {
        $kelas = $this->cariKelas($kode);
        $bab = $item->bab;

        abort_unless(
            $item->tipe === 'file' && $item->path
            && $bab->kelas_id === $kelas->id && $bab->bisaDilihat()
            && Storage::disk('local')->exists($item->path),
            404
        );

        return Storage::disk('local')->response(
            $item->path,
            $item->nama_asli,
            [],
            $request->boolean('unduh') ? 'attachment' : 'inline'
        );
    }

    // Pencarian di dalam satu kelas
    public function cari(Request $request, string $kode)
    {
        $kelas = $this->cariKelas($kode);
        $q = trim((string) $request->query('q'));
        $hasil = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $hasil = $kelas->materiBabs()->terlihat()->with('mapel')
                ->where(function ($w) use ($like) {
                    $w->where('judul', 'like', $like)
                        ->orWhere('deskripsi', 'like', $like)
                        ->orWhereHas('items', fn ($i) => $i->where('judul', 'like', $like)->orWhere('isi', 'like', $like));
                })->get();
        }

        return view('siswa.cari', compact('kelas', 'q', 'hasil'));
    }

    private function cariKelas(string $kode): Kelas
    {
        return Kelas::where('kode_publik', strtolower($kode))->firstOrFail();
    }
}