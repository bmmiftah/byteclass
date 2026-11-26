<?php

namespace App\Http\Controllers;

use App\Models\Pengerjaan;
use App\Models\Ujian;
use App\Services\Anticurang;
use App\Services\KemiripanJawaban;
use Illuminate\Http\Request;

class AnticurangController extends Controller
{
    // simpan pengaturan anti-curang satu ujian
    public function simpan(Request $request, Ujian $ujian)
    {
        $this->milik($ujian);

        $d = $request->validate([
            'batas_pelanggaran' => 'nullable|integer|min:1|max:50',
            'aksi_batas'        => 'required|in:kunci,kumpul',
        ]);

        $ujian->update([
            'blokir_salin'      => $request->boolean('blokir_salin'),
            'layar_penuh'       => $request->boolean('layar_penuh'),
            'batas_pelanggaran' => $d['batas_pelanggaran'] ?? null,
            'aksi_batas'        => $d['aksi_batas'],
        ]);

        return back()->with('ok', 'Pengaturan anti-curang disimpan. Berlaku untuk kejadian berikutnya.');
    }

    // catatan kejadian satu siswa
    public function log(Ujian $ujian, Pengerjaan $pengerjaan)
    {
        $this->milikPengerjaan($ujian, $pengerjaan);

        $pengerjaan->load('siswa');
        $kejadians = $pengerjaan->kejadians()->get();

        return view('ujian.log', [
            'ujian'     => $ujian,
            'pengerjaan' => $pengerjaan,
            'kejadians' => $kejadians,
            'ringkasan' => $kejadians->groupBy('jenis')->map->count(),
        ]);
    }

    // guru membuka kunci siswa yang sedang terkunci
    public function bukaKunci(Request $request, Pengerjaan $pengerjaan)
    {
        $this->milik($pengerjaan->ujian);

        $berhasil = Anticurang::bukaKunci($pengerjaan, auth()->user()->name);

        if ($request->expectsJson()) {
            return response()->json(['ok' => $berhasil]);
        }

        return back()->with('ok', $berhasil ? 'Kunci dibuka. Siswa mendapat jatah pelanggaran baru.' : 'Siswa ini tidak sedang terkunci.');
    }

    // pasangan siswa dengan jawaban mirip
    public function kemiripan(Request $request, Ujian $ujian)
    {
        $this->milik($ujian);

        // ambang bisa diubah lewat alamat; dibatasi supaya tidak aneh
        $pg   = min(1.0, max(0.5, (float) $request->query('pg', KemiripanJawaban::AMBANG_PG)));
        $esai = min(1.0, max(0.4, (float) $request->query('esai', KemiripanJawaban::AMBANG_ESAI)));

        return view('ujian.kemiripan', [
            'ujian' => $ujian->load('kelas'),
            'hasil' => KemiripanJawaban::cari($ujian, $pg, $esai),
            'pg'    => $pg,
            'esai'  => $esai,
        ]);
    }

    private function milik(Ujian $ujian): void
    {
        abort_unless($ujian->guru_id === auth()->id(), 403);
    }

    private function milikPengerjaan(Ujian $ujian, Pengerjaan $pengerjaan): void
    {
        $this->milik($ujian);
        abort_unless($pengerjaan->ujian_id === $ujian->id, 404);
    }
}
