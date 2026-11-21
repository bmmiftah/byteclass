<?php

namespace App\Http\Controllers;

use App\Models\Denah;
use App\Models\DenahTemplate;
use App\Models\Rubrik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PustakaController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $rubrikSaya = $user->rubriks()
            ->withCount('kriterias')
            ->orderBy('nama')
            ->get();

        $rubrikBersama = Rubrik::where('dibagikan', true)
            ->where('guru_id', '!=', $user->id)
            ->with(['guru:id,name', 'kriterias'])
            ->latest()
            ->get();

        $templateSaya = DenahTemplate::where('guru_id', $user->id)
            ->orderBy('nama')
            ->get();

        $templateBersama = DenahTemplate::where('dibagikan', true)
            ->where('guru_id', '!=', $user->id)
            ->with('guru:id,name')
            ->latest()
            ->get();

        $denahSaya = Denah::whereHas('kelas', fn ($q) => $q->where('guru_id', $user->id))
            ->with('kelas:id,nama')
            ->withCount('mejas')
            ->latest()
            ->get();

        $kelasSaya = $user->kelas()
            ->orderBy('nama')
            ->get();

        return view('pustaka.index', compact(
            'rubrikSaya',
            'rubrikBersama',
            'templateSaya',
            'templateBersama',
            'denahSaya',
            'kelasSaya'
        ));
    }

    /* ---------- Rubrik ---------- */

    public function rubrikBagikan(Rubrik $rubrik)
    {
        $rubrik->dibagikan = ! $rubrik->dibagikan;
        $rubrik->save();

        $pesan = $rubrik->dibagikan
            ? 'Rubrik dibagikan ke guru lain.'
            : 'Rubrik kembali pribadi.';

        return back()->with('ok', $pesan);
    }

    // Menyalin rubrik guru lain (yang dibagikan) menjadi rubrik milik sendiri
    public function rubrikAmbil(Rubrik $rubrik)
    {
        abort_unless($rubrik->dibagikan && $rubrik->guru_id !== auth()->id(), 403);

        $baru = auth()->user()->rubriks()->create([
            'nama' => $rubrik->nama . ' (dari ' . $rubrik->guru->name . ')',
        ]);

        foreach ($rubrik->kriterias as $k) {
            $baru->kriterias()->create($k->only(['nama', 'tipe', 'maks', 'bobot', 'urutan']));
        }

        return redirect()
            ->route('rubrik.edit', $baru)
            ->with('ok', 'Rubrik disalin. Silakan sesuaikan bila perlu.');
    }

    /* ---------- Template denah ---------- */

    // Simpan tata letak meja dari sebuah denah menjadi template (tanpa nama siswa)
    public function templateSimpan(Request $request)
    {
        $data = $request->validate([
            'denah_id' => 'required|integer',
            'nama' => 'required|string|max:60',
        ]);

        $denah = Denah::whereHas('kelas', fn ($q) => $q->where('guru_id', auth()->id()))
            ->with('mejas')
            ->findOrFail($data['denah_id']);

        DenahTemplate::create([
            'guru_id' => auth()->id(),
            'nama' => $data['nama'],
            'lebar' => $denah->lebar,
            'tinggi' => $denah->tinggi,
            'meja' => $denah->mejas
                ->map(fn ($m) => [(int) $m->x, (int) $m->y])
                ->values()
                ->all(),
        ]);

        return back()->with('ok', 'Template disimpan.');
    }

    public function templateBagikan(DenahTemplate $template)
    {
        $template->dibagikan = ! $template->dibagikan;
        $template->save();

        $pesan = $template->dibagikan
            ? 'Template dibagikan ke guru lain.'
            : 'Template kembali pribadi.';

        return back()->with('ok', $pesan);
    }

    public function templateHapus(DenahTemplate $template)
    {
        $template->delete();

        return back()->with('ok', 'Template dihapus.');
    }

    // Buat denah baru di salah satu kelas milik sendiri dari template (milik sendiri atau yang dibagikan)
    public function templatePakai(Request $request, DenahTemplate $template)
    {
        abort_unless($template->guru_id === auth()->id() || $template->dibagikan, 403);

        $data = $request->validate([
            'kelas_id' => 'required|integer',
            'nama' => 'required|string|max:60',
        ]);

        $kelas = $request->user()->kelas()->findOrFail($data['kelas_id']);

        $denah = DB::transaction(function () use ($kelas, $template, $data) {
            $denah = $kelas->denahs()->create([
                'nama' => $data['nama'],
                'lebar' => $template->lebar,
                'tinggi' => $template->tinggi,
            ]);

            foreach ($template->meja as [$x, $y]) {
                $denah->mejas()->create(['x' => $x, 'y' => $y]);
            }

            return $denah;
        });

        return redirect()->route('denah.edit', $denah);
    }
}