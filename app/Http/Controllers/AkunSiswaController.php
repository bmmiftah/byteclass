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

        AkunSiswa::firstOrNew(['siswa_id' => $siswa->id])
            ->forceFill([
                'kode'        => AkunSiswa::kodeBaru(),
                'ingat_token' => null, // Ingatan "HP ini" ikut dibatalkan
            ])
            ->save();

        return back()->with('ok', 'Kode ' . $siswa->nama . ' diganti. Unduh atau cetak ulang kartunya.');
    }

    // Kartu "Kode Masuk" digital (PNG) untuk satu kelas
    public function kodeMasuk(Kelas $kelas)
    {
        $this->milik($kelas);

        $siswas = $kelas->siswas()
            ->where('aktif', true)
            ->with('akun')
            ->orderBy('nama')
            ->get();

        $situs = preg_replace('#^https?://#', '', rtrim(url('/'), '/'));

        $kartu = $siswas
            ->filter(fn ($s) => $s->akun)
            ->map(fn ($s) => [
                'nama'        => $s->nama,
                'kelas'       => $kelas->nama,
                'warna'       => $kelas->warna,
                'situs'       => $situs,
                'kodeKelas'   => $kelas->kode_tampil,
                'kodePribadi' => $s->akun->kode_tampil,
            ])
            ->values();

        $tanpaKode = $siswas->count() - $kartu->count();

        return view('akun.kode-masuk', compact('kelas', 'kartu', 'tanpaKode'));
    }

    private function milik(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}