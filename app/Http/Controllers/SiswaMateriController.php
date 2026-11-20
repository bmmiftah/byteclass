<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\MateriBab;
use App\Models\MateriItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiswaMateriController extends Controller
{
    // Beranda website siswa: cukup masukkan kode akses
    public function beranda(Request $request)
    {
        $kode = preg_replace('/[^a-z0-9]/', '', strtolower((string) $request->query('kode')));

        if ($kode === '') {
            return view('siswa.beranda', ['galat' => null]);
        }

        $kelas = Kelas::where('kode_publik', $kode)->first();

        if ($kelas) {
            return redirect()->route('siswa.kelas', $kelas->kode_publik);
        }

        return view('siswa.beranda', ['galat' => 'Kode akses tidak ditemukan. Periksa lagi ya.']);
    }

    // Dashboard kelas
    public function kelas(string $kode)
    {
        $kelas = $this->cariKelas($kode);
        $semua = $kelas->materiTampil()->terlihat()->with('mapel')->withCount('items')->get();
        $terbaru = $semua->sortByDesc(fn ($b) => $b->terbit_pada ?? $b->created_at)->take(3)->values();
        $babs = $semua->groupBy(fn ($b) => $b->mapel?->nama ?? 'Umum');
        $jumlahMateri = $semua->sum('items_count');

        return view('siswa.kelas', compact('kelas', 'babs', 'terbaru', 'jumlahMateri'))
            ->with('jumlahBab', $semua->count());
    }

    // Daftar materi dalam satu bab
    public function bab(string $kode, MateriBab $bab)
    {
        $kelas = $this->cariKelas($kode);
        abort_unless($bab->tersediaUntuk($kelas) && $bab->bisaDilihat(), 404);
        $bab->load('items', 'mapel');

        return view('siswa.bab', compact('kelas', 'bab'));
    }

    // Satu materi di halaman sendiri
    public function item(string $kode, MateriItem $item)
    {
        $kelas = $this->cariKelas($kode);
        $bab = $item->bab;
        abort_unless($bab->tersediaUntuk($kelas) && $bab->bisaDilihat(), 404);

        $items = $bab->items;
        $i = $items->search(fn ($x) => $x->id === $item->id);
        $sebelum = $i > 0 ? $items[$i - 1] : null;
        $sesudah = $items[$i + 1] ?? null;

        return view('siswa.item', compact('kelas', 'bab', 'item', 'sebelum', 'sesudah'))
            ->with('nomor', $i + 1)
            ->with('total', $items->count());
    }

    // Menyajikan file privat setelah dicek (bab harus terbit dan tersedia untuk kelas ini)
    public function berkas(Request $request, string $kode, MateriItem $item)
    {
        $kelas = $this->cariKelas($kode);
        $bab = $item->bab;

        abort_unless(
            $item->tipe === 'file' && $item->path
            && $bab->tersediaUntuk($kelas) && $bab->bisaDilihat()
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

    // Gambar yang disisipkan di dalam teks materi (nama acak, tidak bisa ditebak)
    public function gambar(string $nama)
    {
        $path = 'materi-gambar/'.$nama;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    // Pencarian di dalam satu kelas
    public function cari(Request $request, string $kode)
    {
        $kelas = $this->cariKelas($kode);
        $q = trim((string) $request->query('q'));
        $hasil = collect();

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $hasil = $kelas->materiTampil()->terlihat()->with('mapel')
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