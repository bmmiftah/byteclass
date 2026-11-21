<?php

namespace App\Http\Controllers;

use App\Models\AkunSiswa;
use App\Models\Ujian;
use App\Support\SiswaSesi;
use Illuminate\Http\Request;

class SayaController extends Controller
{
    public function masukForm(Request $request)
    {
        // Kalau masih "diingat" dari kunjungan sebelumnya, langsung masuk
        if (SiswaSesi::saatIni($request)) {
            return redirect()->route('saya.beranda');
        }

        return view('saya.masuk', ['kode' => $request->query('kode')]);
    }

    public function masuk(Request $request)
    {
        $request->validate(['kode' => 'required|string|max:20']);

        $akun = AkunSiswa::where('kode', AkunSiswa::bersihkan($request->input('kode')))->first();

        if (! $akun || ! $akun->siswa || ! $akun->siswa->aktif) {
            return back()
                ->withInput($request->only('kode'))
                ->withErrors(['kode' => 'Kode tidak dikenal. Periksa lagi kode pribadimu.']);
        }

        SiswaSesi::masukkan($request, $akun, $request->boolean('ingat'));

        return redirect()->route('saya.beranda');
    }

    public function beranda(Request $request)
    {
        $akun = $request->attributes->get('akun');
        $siswa = $akun->siswa;
        $kelas = $siswa->kelas;
        $ujianBuka = Ujian::where('kelas_id', $kelas->id)
            ->where('status', 'buka')
            ->count();

        return view('saya.beranda', compact('akun', 'siswa', 'kelas', 'ujianBuka'));
    }

    public function keluar(Request $request)
    {
        SiswaSesi::keluar($request);

        return redirect()->route('siswa.beranda');
    }
}