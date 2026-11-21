<?php

namespace App\Http\Controllers;

use App\Models\Catatan;
use App\Models\Kelas;
use Illuminate\Http\Request;

class CatatanController extends Controller
{
    public function index(Kelas $kelas)
    {
        $this->milik($kelas);

        $siswas = $kelas->siswas()
            ->where('aktif', true)
            ->orderBy('nama')
            ->withSum('catatans as total_poin', 'poin')
            ->get();

        $total = $siswas->mapWithKeys(fn ($s) => [$s->id => (int) $s->total_poin]);

        $terbaru = Catatan::where('kelas_id', $kelas->id)
            ->with('siswa')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('catatan.index', compact('kelas', 'siswas', 'total', 'terbaru'));
    }

    public function store(Request $request, Kelas $kelas)
    {
        $this->milik($kelas);

        $data = $request->validate([
            'siswa_id' => 'required|integer',
            'jenis' => 'required|in:plus,minus,catatan',
            'poin' => 'nullable|integer|min:0|max:100',
            'isi' => 'nullable|string|max:1000',
            'tanggal' => 'nullable|date',
        ]);

        // siswa harus dari kelas ini
        $siswa = $kelas->siswas()->findOrFail($data['siswa_id']);

        $poin = abs((int) ($data['poin'] ?? 0));
        $poin = match ($data['jenis']) {
            'plus' => $poin,
            'minus' => -$poin,
            default => 0,
        };

        Catatan::create([
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'tanggal' => $data['tanggal'] ?? today()->toDateString(),
            'jenis' => $data['jenis'],
            'poin' => $poin,
            'isi' => $data['isi'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'total' => (int) Catatan::where('siswa_id', $siswa->id)->sum('poin'),
            ]);
        }

        return back()->with('ok', 'Catatan disimpan untuk '.$siswa->nama.'.');
    }

    public function destroy(Catatan $catatan)
    {
        $this->milik($catatan->siswa->kelas);
        $catatan->delete();

        return back()->with('ok', 'Catatan dihapus.');
    }

    private function milik(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}