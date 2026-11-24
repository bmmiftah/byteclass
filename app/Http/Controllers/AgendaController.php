<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Agenda kelas (sisi guru). Route-nya berada di grup ['auth','aktif','milik']. */
class AgendaController extends Controller
{
    public function index(Kelas $kelas)
    {
        $akan = Agenda::where('kelas_id', $kelas->id)
            ->where(fn ($q) => $q->where('mulai', '>=', today())->orWhere('selesai', '>=', today()))
            ->orderBy('mulai')
            ->get();

        $lalu = Agenda::where('kelas_id', $kelas->id)
            ->where('mulai', '<', today())
            ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '<', today()))
            ->orderByDesc('mulai')
            ->limit(20)
            ->get();

        return view('agenda.index', compact('kelas', 'akan', 'lalu'));
    }

    public function create(Kelas $kelas)
    {
        return view('agenda.form', [
            'kelas' => $kelas,
            'a'     => new Agenda(['jenis' => 'kegiatan', 'seharian' => true]),
        ]);
    }

    public function store(Request $request, Kelas $kelas)
    {
        $a = new Agenda($this->validasi($request));
        $a->kelas_id = $kelas->id;
        $a->save();

        return redirect()
            ->route('agenda.index', $kelas)
            ->with('ok', 'Agenda disimpan.');
    }

    public function edit(Agenda $agenda)
    {
        return view('agenda.form', ['kelas' => $agenda->kelas, 'a' => $agenda]);
    }

    public function update(Request $request, Agenda $agenda)
    {
        $agenda->update($this->validasi($request));

        return redirect()
            ->route('agenda.index', $agenda->kelas_id)
            ->with('ok', 'Agenda diperbarui.');
    }

    public function destroy(Agenda $agenda)
    {
        $kelasId = $agenda->kelas_id;
        $agenda->delete();

        return redirect()
            ->route('agenda.index', $kelasId)
            ->with('ok', 'Agenda dihapus.');
    }

    /** Tanggal dan jam dari formulir digabung jadi waktu mulai/selesai. Jam mulai kosong = sepanjang hari. */
    private function validasi(Request $request): array
    {
        $d = $request->validate([
            'judul'           => 'required|string|max:150',
            'keterangan'      => 'nullable|string|max:1000',
            'jenis'           => 'required|in:' . implode(',', array_keys(Agenda::JENIS)),
            'tanggal_mulai'   => 'required|date_format:Y-m-d',
            'jam_mulai'       => 'nullable|date_format:H:i',
            'tanggal_selesai' => 'nullable|date_format:Y-m-d|after_or_equal:tanggal_mulai',
            'jam_selesai'     => 'nullable|date_format:H:i',
        ], [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $seharian = empty($d['jam_mulai']);
        $mulai = Carbon::parse($d['tanggal_mulai'] . ' ' . ($seharian ? '00:00' : $d['jam_mulai']));
        $selesai = null;

        if (! empty($d['tanggal_selesai']) || (! $seharian && ! empty($d['jam_selesai']))) {
            $tanggalAkhir = $d['tanggal_selesai'] ?? $d['tanggal_mulai'];
            $jamAkhir = $seharian ? '00:00' : ($d['jam_selesai'] ?? $d['jam_mulai']);
            $selesai = Carbon::parse($tanggalAkhir . ' ' . $jamAkhir);

            if ($selesai->lt($mulai)) {
                $selesai = null; // Jam selesai sebelum jam mulai di hari yang sama: abaikan
            }
        }

        return [
            'judul'      => $d['judul'],
            'keterangan' => ($d['keterangan'] ?? '') !== '' ? $d['keterangan'] : null,
            'jenis'      => $d['jenis'],
            'mulai'      => $mulai,
            'selesai'    => $selesai,
            'seharian'   => $seharian,
        ];
    }
}