<?php

namespace App\Http\Controllers;

use App\Models\Pengumuman;
use App\Services\KalenderKelas;
use Illuminate\Http\Request;

/** Kalender, agenda, dan pengumuman untuk siswa (di bawah /saya, dijaga middleware SiswaMasuk). */
class SayaKalenderController extends Controller
{
    public function kalender(Request $request)
    {
        $siswa = $request->attributes->get('akun')->siswa;
        $kelas = $siswa->kelas;
        $bulan = KalenderKelas::bulanDari($request->query('bulan'));

        $kejadian = KalenderKelas::kejadian(
            $kelas,
            $bulan->copy()->startOfMonth()->startOfWeek(),
            $bulan->copy()->endOfMonth()->endOfWeek(),
            $siswa
        );

        $minggu = KalenderKelas::grid($bulan, $kejadian);
        $dipilih = now()->isSameMonth($bulan) ? now()->format('Y-m-d') : $bulan->format('Y-m-01');

        return view('saya.kalender', [
            'siswa'   => $siswa,
            'kelas'   => $kelas,
            'bulan'   => $bulan,
            'minggu'  => $minggu,
            'dipilih' => $dipilih,
            'sebelum' => $bulan->copy()->subMonth()->format('Y-m'),
            'sesudah' => $bulan->copy()->addMonth()->format('Y-m'),
        ]);
    }

    public function agenda(Request $request)
    {
        $siswa = $request->attributes->get('akun')->siswa;
        $kelas = $siswa->kelas;

        $akan = KalenderKelas::kejadian($kelas, today(), today()->addDays(30), $siswa)->groupBy('tanggal');
        $lalu = KalenderKelas::kejadian($kelas, today()->subDays(7), today()->subDay(), $siswa)
            ->groupBy('tanggal')
            ->reverse();

        return view('saya.agenda', compact('siswa', 'kelas', 'akan', 'lalu'));
    }

    public function pengumuman(Request $request)
    {
        $siswa = $request->attributes->get('akun')->siswa;
        $kelas = $siswa->kelas;
        $daftar = Pengumuman::tayang()->where('kelas_id', $kelas->id)->urut()->limit(50)->get();

        return view('saya.pengumuman', compact('siswa', 'kelas', 'daftar'));
    }
}