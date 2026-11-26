<?php

namespace App\Http\Controllers;

use App\Models\GamifikasiPengaturan;
use App\Services\Gamifikasi;
use Illuminate\Http\Request;

/** Halaman Poin di Dashboard Saya. Siswa hanya melihat kelasnya sendiri, dan hanya bila gamifikasi dihidupkan guru. */
class SayaPoinController extends Controller
{
    public function index(Request $request)
    {
        $akun = $request->attributes->get('akun');
        $siswa = $akun->siswa;
        $kelas = $siswa->kelas;
        $p = GamifikasiPengaturan::where('kelas_id', $kelas->id)->where('aktif', true)->first();
        abort_unless($p, 404);

        Gamifikasi::sinkron($kelas);
        $data = Gamifikasi::untukSiswa($siswa, $p);

        return view('saya.poin', $data + compact('akun', 'siswa', 'kelas', 'p'));
    }
}