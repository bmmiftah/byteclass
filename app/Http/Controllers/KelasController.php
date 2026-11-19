<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function index()
    {
        $kelas = auth()->user()->kelas()->withCount('siswas')->latest()->get();

        return view('kelas.index', compact('kelas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:50',
            'tahun_ajaran' => 'required|string|max:9',
            'semester' => 'required|in:1,2',
        ]);

        auth()->user()->kelas()->create($data);

        return back()->with('ok', 'Kelas ditambahkan.');
    }

    public function show(Kelas $kelas)
    {
        $this->milik($kelas);

        $siswas = $kelas->siswas()->orderBy('nama')->get();

        return view('kelas.show', compact('kelas', 'siswas'));
    }

    public function destroy(Kelas $kelas)
    {
        $this->milik($kelas);

        $kelas->delete();

        return redirect()->route('kelas.index')->with('ok', 'Kelas dihapus.');
    }

    // Pengaman: guru hanya boleh membuka kelas miliknya
    private function milik(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}