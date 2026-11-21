<?php

namespace App\Http\Controllers;

use App\Exports\TabelExport;
use App\Models\Kelas;
use App\Models\Presensi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PresensiController extends Controller
{
    public function index(Request $request, Kelas $kelas)
    {
        $this->milik($kelas);
        $tanggal = $this->tanggal($request->query('tanggal'));
        $siswas = $kelas->siswas()->where('aktif', true)->orderBy('nama')->get();
        $tersimpan = Presensi::where('kelas_id', $kelas->id)
            ->whereDate('tanggal', $tanggal)
            ->pluck('status', 'siswa_id');

        // semua dianggap Hadir sampai diketuk
        $awal = $siswas->mapWithKeys(fn ($sw) => [$sw->id => $tersimpan[$sw->id] ?? 'H']);

        return view('presensi.index', [
            'kelas' => $kelas,
            'siswas' => $siswas,
            'tanggal' => $tanggal,
            'awal' => $awal,
            'sudah' => $tersimpan->isNotEmpty(),
        ]);
    }

    public function simpan(Request $request, Kelas $kelas)
    {
        $this->milik($kelas);
        $data = $request->validate([
            'tanggal' => 'required|date',
            'status' => 'required|array',
            'status.*' => 'in:H,S,I,A',
        ]);

        $tanggal = Carbon::parse($data['tanggal'])->toDateString();
        $sah = $kelas->siswas()->pluck('id')->all(); // hanya siswa kelas ini

        foreach ($data['status'] as $siswaId => $status) {
            if (! in_array((int) $siswaId, $sah, true)) {
                continue;
            }

            Presensi::updateOrCreate(
                ['siswa_id' => (int) $siswaId, 'tanggal' => $tanggal],
                ['kelas_id' => $kelas->id, 'status' => $status]
            );
        }

        return redirect()->route('presensi.index', [$kelas, 'tanggal' => $tanggal])
            ->with('ok', 'Presensi tanggal '.Carbon::parse($tanggal)->translatedFormat('d M Y').' disimpan.');
    }

    public function rekap(Request $request, Kelas $kelas)
    {
        $this->milik($kelas);
        $bulan = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('bulan'))
            ? $request->query('bulan')
            : now()->format('Y-m');

        $awal = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();
        $siswas = $kelas->siswas()->where('aktif', true)->orderBy('nama')->get();

        $rentang = fn () => Presensi::where('kelas_id', $kelas->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()]);

        $hitung = $rentang()->selectRaw('siswa_id, status, count(*) as n')
            ->groupBy('siswa_id', 'status')
            ->get()
            ->groupBy('siswa_id');

        $jumlahHari = $rentang()->distinct()->count('tanggal');

        $baris = $siswas->map(function ($sw, $i) use ($hitung) {
            $g = ($hitung[$sw->id] ?? collect())->pluck('n', 'status');
            $h = (int) ($g['H'] ?? 0);
            $s = (int) ($g['S'] ?? 0);
            $iz = (int) ($g['I'] ?? 0);
            $a = (int) ($g['A'] ?? 0);
            $total = $h + $s + $iz + $a;

            return [
                $i + 1,
                $sw->nama,
                $h,
                $s,
                $iz,
                $a,
                $total ? round($h / $total * 100).'%' : '-',
            ];
        })->all();

        if ($request->boolean('unduh')) {
            return Excel::download(
                new TabelExport(['No', 'Nama', 'H', 'S', 'I', 'A', '% Hadir'], $baris),
                'presensi-'.Str::slug($kelas->nama).'-'.$bulan.'.xlsx'
            );
        }

        return view('presensi.rekap', compact('kelas', 'bulan', 'baris', 'jumlahHari'));
    }

    private function tanggal($nilai): string
    {
        try {
            return Carbon::parse($nilai ?: today()->toDateString())->toDateString();
        } catch (\Throwable $e) {
            return today()->toDateString();
        }
    }

    private function milik(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}