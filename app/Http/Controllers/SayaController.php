<?php

namespace App\Http\Controllers;

use App\Models\AkunSiswa;
use App\Models\Pengumuman;
use App\Models\Tugas;
use App\Models\TugasPengumpulan;
use App\Models\Ujian;
use App\Services\KalenderKelas;
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
                ->withErrors(['kode' => 'Kode tidak dikenali. Periksa lagi kode pribadimu.']);
        }

        SiswaSesi::masukkan($request, $akun, $request->boolean('ingat'));

        return redirect()->route('saya.beranda');
    }

    public function beranda(Request $request)
    {
        $akun = $request->attributes->get('akun');
        $siswa = $akun->siswa;
        $kelas = $siswa->kelas;

        $ujianBuka = Ujian::untukSiswa($siswa->id)->where('kelas_id', $kelas->id)->where('status', 'buka')->count();

        // Tugas yang masih bisa dikerjakan siswa ini, tenggat terdekat dulu
        $terbit = Tugas::terlihat()
            ->where('kelas_id', $kelas->id)
            ->where('status', 'terbit')
            ->orderBy('tenggat')
            ->get();

        $sudah = TugasPengumpulan::where('siswa_id', $siswa->id)
            ->whereIn('tugas_id', $terbit->pluck('id'))
            ->pluck('tugas_id');

        $tugasPerlu = $terbit
            ->reject(fn ($t) => $sudah->contains($t->id))
            ->filter(fn ($t) => $t->bolehKumpul())
            ->values();

        // Pengumuman yang sedang tayang (sematan di atas) dan kejadian 7 hari ke depan
        $pengumuman = Pengumuman::tayang()
            ->where('kelas_id', $kelas->id)
            ->urut()
            ->limit(3)
            ->get();

        $mendatang = KalenderKelas::kejadian($kelas, today(), today()->addDays(7), $siswa)
            ->reject(fn ($e) => $e['selesai'] === true)
            ->take(8)
            ->values();

        return view('saya.beranda', compact(
            'akun',
            'siswa',
            'kelas',
            'ujianBuka',
            'tugasPerlu',
            'pengumuman',
            'mendatang'
        ));
    }

    public function keluar(Request $request)
    {
        SiswaSesi::keluar($request);

        return redirect()->route('siswa.beranda');
    }
}