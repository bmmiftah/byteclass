<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use Illuminate\Http\Request;
use	Illuminate\Validation\Rule;

class KelasController extends Controller
{
    public function index()
    {
        $kelas = auth()->user()->kelas()
            ->where('arsip', false)
            ->withCount('siswas')
            ->latest()
            ->get();

        return view('kelas.index', compact('kelas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
        'nama'         => 'required|string|max:50',
        'tahun_ajaran' => 'required|string|max:9',
        'semester'     => 'required|in:1,2',
        'warna'        => ['nullable', Rule::in(array_keys(Kelas::WARNA))],
    ]);

    // Tidak memilih warna = diberi otomatis, bergantian
    $data['warna'] = $data['warna'] ?? Kelas::warnaBerikut(auth()->id());

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

    public function warna(Request $request, Kelas $kelas)
    {
        $this->milik($kelas);

        $data = $request->validate([
            'warna' => ['required', Rule::in(array_keys(Kelas::WARNA))],
        ]);

        $kelas->update($data);

        return back()->with('ok', 'Warna kelas diubah.');
    }

    // Pengaman: guru hanya boleh membuka kelas miliknya
    private function milik(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }

    // Arsip Tahun Ajaran
    public function arsip()
    {
        $kelas = auth()->user()->kelas()
            ->where('arsip', true)
            ->withCount('siswas')
            ->latest()
            ->get();

        return view('kelas.arsip', compact('kelas'));
    }

    public function arsipkan(Kelas $kelas)
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);

        $kelas->forceFill(['arsip' => ! $kelas->arsip])->save();

        return redirect()
            ->route($kelas->arsip ? 'kelas.index' : 'kelas.arsip')
            ->with('ok', $kelas->arsip ? 'Kelas diarsipkan.' : 'Kelas dipulihkan.');
    }
}