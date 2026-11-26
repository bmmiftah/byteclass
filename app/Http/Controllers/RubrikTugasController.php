<?php

namespace App\Http\Controllers;

use App\Models\Rubrik;
use App\Models\Tugas;
use App\Services\RubrikTugas;
use Illuminate\Http\Request;

/** Memasang dan melepas rubrik pada tugas. Route-nya di grup ['auth','aktif','milik'] sehingga {tugas} sudah dijaga. */
class RubrikTugasController extends Controller
{
    public function pasang(Request $request, Tugas $tugas)
    {
        $d = $request->validate(
            ['rubrik_id' => 'required|integer'],
            ['rubrik_id.required' => 'Pilih rubrik dulu.']
        );

        // Hanya rubrik milik guru yang login
        $rubrik = Rubrik::where('guru_id', $request->user()->id)->find($d['rubrik_id']);
        if (! $rubrik) {
            return back()->withErrors(['rubrik' => 'Rubrik tidak ditemukan.']);
        }

        if ($tugas->kriterias()->exists()) {
            return back()->withErrors(['rubrik' => 'Tugas ini sudah memakai rubrik. Lepas dulu kalau mau mengganti.']);
        }

        $n = RubrikTugas::pasang($tugas, $rubrik);
        if ($n === null) {
            return back()->withErrors(['rubrik' => 'Rubrik "' . $rubrik->nama . '" belum punya kriteria.']);
        }

        return back()->with('ok', 'Rubrik "' . $rubrik->nama . '" dipasang (' . $n . ' kriteria). Nilai sekarang diisi per kriteria.');
    }

    public function lepas(Tugas $tugas)
    {
        RubrikTugas::lepas($tugas);

        return back()->with('ok', 'Rubrik dilepas. Nilai total siswa tetap tersimpan; rincian per kriteria dihapus.');
    }
}