<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Pengumuman;
use Illuminate\Http\Request;

/** Pengumuman kelas (sisi guru). Route-nya berada di grup ['auth','aktif','milik']. */
class PengumumanController extends Controller
{
    public function index(Kelas $kelas)
    {
        $daftar = Pengumuman::where('kelas_id', $kelas->id)->urut()->get();

        return view('pengumuman.index', compact('kelas', 'daftar'));
    }

    public function create(Kelas $kelas)
    {
        return view('pengumuman.form', ['kelas' => $kelas, 'p' => new Pengumuman()]);
    }

    public function store(Request $request, Kelas $kelas)
    {
        $p = new Pengumuman($this->validasi($request));
        $p->kelas_id = $kelas->id;
        $p->save();

        return redirect()
            ->route('pengumuman.index', $kelas)
            ->with('ok', 'Pengumuman disimpan.');
    }

    public function edit(Pengumuman $pengumuman)
    {
        return view('pengumuman.form', ['kelas' => $pengumuman->kelas, 'p' => $pengumuman]);
    }

    public function update(Request $request, Pengumuman $pengumuman)
    {
        $pengumuman->update($this->validasi($request));

        return redirect()
            ->route('pengumuman.index', $pengumuman->kelas_id)
            ->with('ok', 'Pengumuman diperbarui.');
    }

    public function destroy(Pengumuman $pengumuman)
    {
        $kelasId = $pengumuman->kelas_id;
        $pengumuman->delete();

        return redirect()
            ->route('pengumuman.index', $kelasId)
            ->with('ok', 'Pengumuman dihapus.');
    }

    private function validasi(Request $request): array
    {
        $d = $request->validate([
            'judul'         => 'required|string|max:150',
            'isi'           => 'required|string|max:3000',
            'terbit_pada'   => 'nullable|date',
            'berakhir_pada' => 'nullable|date|after:terbit_pada',
        ], [
            'berakhir_pada.after' => 'Waktu berakhir harus setelah waktu tayang.',
        ]);

        $d['disematkan']    = $request->boolean('disematkan');
        $d['terbit_pada']   = $d['terbit_pada'] ?? null;
        $d['berakhir_pada'] = $d['berakhir_pada'] ?? null;

        return $d;
    }
}