<?php

namespace App\Http\Controllers;

use App\Models\Rubrik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RubrikController extends Controller
{
    public function index()
    {
        $rubriks = auth()->user()->rubriks()->withCount('kriterias')->orderBy('nama')->get();

        return view('rubrik.index', compact('rubriks'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['nama' => 'required|string|max:80']);

        $rubrik = auth()->user()->rubriks()->create($data);
        $rubrik->kriterias()->create([
            'nama' => 'Kriteria 1',
            'tipe' => 'angka',
            'maks' => 100,
            'bobot' => 1,
            'urutan' => 0,
        ]);

        return redirect()->route('rubrik.edit', $rubrik);
    }

    public function edit(Rubrik $rubrik)
    {
        $this->milik($rubrik);

        $data = [
            'url' => route('rubrik.update', $rubrik),
            'nama' => $rubrik->nama,
            'kriterias' => $this->daftarKriteria($rubrik),
        ];

        return view('rubrik.edit', compact('rubrik', 'data'));
    }

    public function update(Request $request, Rubrik $rubrik)
    {
        $this->milik($rubrik);

        $v = $request->validate([
            'nama' => 'required|string|max:80',
            'kriterias' => 'required|array|min:1|max:30',
            'kriterias.*.nama' => 'required|string|max:80',
            'kriterias.*.tipe' => 'required|in:angka,poin,ceklis',
            'kriterias.*.maks' => 'required|integer|min:1|max:1000',
            'kriterias.*.bobot' => 'required|integer|min:1|max:100',
        ]);

        DB::transaction(function () use ($rubrik, $v) {
            $rubrik->update(['nama' => $v['nama']]);
            $rubrik->kriterias()->delete();

            foreach ($v['kriterias'] as $urutan => $k) {
                $maks = (int) $k['maks'];

                if ($k['tipe'] === 'ceklis') {
                    $maks = 1;
                } elseif ($k['tipe'] === 'poin') {
                    $maks = max(2, min(10, $maks));
                }

                $rubrik->kriterias()->create([
                    'nama' => $k['nama'],
                    'tipe' => $k['tipe'],
                    'maks' => $maks,
                    'bobot' => $k['bobot'],
                    'urutan' => $urutan,
                ]);
            }
        });

        return response()->json([
            'nama' => $rubrik->nama,
            'kriterias' => $this->daftarKriteria($rubrik->fresh()),
        ]);
    }

    public function salin(Rubrik $rubrik)
    {
        $this->milik($rubrik);

        $baru = auth()->user()->rubriks()->create(['nama' => $rubrik->nama.' (salinan)']);

        foreach ($rubrik->kriterias as $k) {
            $baru->kriterias()->create($k->only(['nama', 'tipe', 'maks', 'bobot', 'urutan']));
        }

        return redirect()->route('rubrik.edit', $baru);
    }

    public function destroy(Rubrik $rubrik)
    {
        $this->milik($rubrik);

        $rubrik->delete(); // penilaian lama tetap aman: kriterianya sudah disalin

        return redirect()->route('rubrik.index')->with('ok', 'Rubrik dihapus.');
    }

    private function daftarKriteria(Rubrik $rubrik)
    {
        return $rubrik->kriterias->map(fn ($k) => $k->only(['nama', 'tipe', 'maks', 'bobot']))->values();
    }

    private function milik(Rubrik $rubrik): void
    {
        abort_unless($rubrik->guru_id === auth()->id(), 403);
    }
}