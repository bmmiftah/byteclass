<?php

namespace App\Http\Controllers;

use App\Exports\TabelExport;
use App\Models\Jurnal;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class JurnalController extends Controller
{
    public function index(Request $request)
    {
        $kelas = auth()->user()->kelas()
            ->where('arsip', false)
            ->orderBy('nama')
            ->get();

        $mapels = auth()->user()->mapels()
            ->orderBy('nama')
            ->get();

        $bulan = preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) $request->query('bulan'), $m)
            ? $m
            : null;

        $query = Jurnal::where('guru_id', auth()->id())
            ->with('kelas', 'mapel')
            ->when($request->filled('kelas_id'), fn ($q) => $q->where('kelas_id', (int) $request->query('kelas_id')))
            ->when($bulan, fn ($q) => $q->whereYear('tanggal', (int) $bulan[1])->whereMonth('tanggal', (int) $bulan[2]))
            ->orderByDesc('tanggal')
            ->orderByDesc('id');

        if ($request->boolean('unduh')) {
            $baris = $query->get()
                ->map(fn ($j) => [
                    $j->tanggal->format('d/m/Y'),
                    $j->kelas?->nama,
                    $j->mapel?->nama,
                    $j->topik,
                    $j->kegiatan,
                    $j->kendala,
                    $j->tindak_lanjut,
                ])
                ->all();

            return Excel::download(
                new TabelExport(
                    ['Tanggal', 'Kelas', 'Mapel', 'Topik', 'Kegiatan', 'Kendala', 'Tindak lanjut'],
                    $baris
                ),
                'jurnal-mengajar.xlsx'
            );
        }

        $jurnals = $query->paginate(15)->withQueryString();
        $baru = new Jurnal(['tanggal' => today()]);

        return view('jurnal.index', compact('kelas', 'mapels', 'jurnals', 'baru'));
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);

        Jurnal::create($data + ['guru_id' => auth()->id()]);

        return redirect()->route('jurnal.index')->with('ok', 'Jurnal disimpan.');
    }

    public function edit(Jurnal $jurnal)
    {
        $this->milik($jurnal);

        $kelas = auth()->user()->kelas()->orderBy('nama')->get();
        $mapels = auth()->user()->mapels()->orderBy('nama')->get();

        return view('jurnal.edit', compact('jurnal', 'kelas', 'mapels'));
    }

    public function update(Request $request, Jurnal $jurnal)
    {
        $this->milik($jurnal);

        $jurnal->update($this->validasi($request));

        return redirect()->route('jurnal.index')->with('ok', 'Jurnal diperbarui.');
    }

    public function destroy(Jurnal $jurnal)
    {
        $this->milik($jurnal);
        $jurnal->delete();

        return back()->with('ok', 'Jurnal dihapus.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'kelas_id' => 'required|integer',
            'mapel_id' => 'nullable|integer',
            'tanggal' => 'required|date',
            'topik' => 'required|string|max:200',
            'kegiatan' => 'nullable|string|max:3000',
            'kendala' => 'nullable|string|max:3000',
            'tindak_lanjut' => 'nullable|string|max:3000',
        ]);

        // Kelas dan mapel harus milik guru yang sedang login
        $data['kelas_id'] = auth()->user()->kelas()->findOrFail($data['kelas_id'])->id;
        $data['mapel_id'] = $data['mapel_id']
            ? auth()->user()->mapels()->whereKey($data['mapel_id'])->value('id')
            : null;

        return $data;
    }

    private function milik(Jurnal $jurnal): void
    {
        abort_unless($jurnal->guru_id === auth()->id(), 403);
    }
}