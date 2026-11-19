<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use	App\Imports\SiswaImport;
use	Maatwebsite\Excel\Facades\Excel;

class SiswaController extends Controller
{
    public function store(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);

        $data = $request->validate([
            'nama'           => 'required|string|max:100',
            'nis'            => 'nullable|string|max:30',
            'nama_panggilan' => 'nullable|string|max:50',
            'gender'         => 'nullable|in:L,P',
        ]);

        $kelas->siswas()->create($data);

        return back()->with('ok', 'Siswa ditambahkan.');
    }

    public function destroy(Siswa $siswa)
    {
        abort_unless($siswa->kelas->guru_id === auth()->id(), 403);

        $siswa->delete();

        return back()->with('ok', 'Siswa dihapus.');
    }

    public function import(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        Excel::import(new SiswaImport($kelas->id), $request->file('file'));

        return back()->with('ok', 'Siswa berhasil diimpor.');
    }
}