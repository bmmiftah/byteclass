<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Presensi;
use Illuminate\Support\Str;

class AlatController extends Controller
{
    public function index(Kelas $kelas)
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);

        // siswa yang tidak hadir hari ini
        $absen = Presensi::where('kelas_id', $kelas->id)
            ->whereDate('tanggal', today())
            ->where('status', '!=', 'H')
            ->pluck('siswa_id')
            ->all();

        $daftar = $kelas->siswas()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'nama' => $s->nama_panggilan ?: Str::words($s->nama, 1, ''),
                'hadir' => ! in_array($s->id, $absen, true),
            ])
            ->values();

        return view('alat.index', compact('kelas', 'daftar'));
    }
}