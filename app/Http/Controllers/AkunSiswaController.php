<?php

namespace App\Http\Controllers;

use App\Models\AkunSiswa;
use App\Models\Kelas;
use App\Models\Siswa;

class AkunSiswaController extends Controller
{
    public function index(Kelas $kelas)
    {
        $this->milik($kelas);

        $siswas = $kelas->siswas()
            ->where('aktif', true)
            ->with('akun')
            ->orderBy('nama')
            ->get();

        return view('akun.index', compact('kelas', 'siswas'));
    }

    // Buat kode untuk siswa yang belum punya (yang sudah punya tidak diubah)
    public function buat(Kelas $kelas)
    {
        $this->milik($kelas);

        $jumlah = 0;

        $kelas->siswas()
            ->where('aktif', true)
            ->doesntHave('akun')
            ->get()
            ->each(function ($s) use (&$jumlah) {
                AkunSiswa::create([
                    'siswa_id' => $s->id,
                    'kode' => AkunSiswa::kodeBaru(),
                ]);
                $jumlah++;
            });

        return back()->with('ok', $jumlah.' kode baru dibuat.');
    }

    // Ganti kode satu siswa (kartu lama tidak berlaku lagi)
    public function reset(Siswa $siswa)
    {
        $this->milik($siswa->kelas);

        AkunSiswa::updateOrCreate(
            ['siswa_id' => $siswa->id],
            ['kode' => AkunSiswa::kodeBaru()]
        );

        return back()->with('ok', 'Kode '.$siswa->nama.' diganti. Cetak ulang kartunya.');
    }

    public function kartu(Kelas $kelas)
    {
        $this->milik($kelas);

        $siswas = $kelas->siswas()
            ->where('aktif', true)
            ->has('akun')
            ->with('akun')
            ->orderBy('nama')
            ->get();

        return view('akun.kartu', compact('kelas', 'siswas'));
    }

    private function milik(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}