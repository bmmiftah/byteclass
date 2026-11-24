<?php

namespace App\Http\Controllers;

use App\Exports\TabelExport;
use App\Models\Ujian;
use App\Services\AnalisisUjian;
use App\Services\KoreksiUjian;
use App\Services\LegerNilai;
use App\Services\RemedialUjian;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class AnalisisController extends Controller
{
    /** KKM: dari ?kkm= bila valid, kalau tidak dari pengaturan Leger kelas dan mapel ujian ini. */
    private function kkm(Request $r, Ujian $ujian): int
    {
        $k = $r->query('kkm');
        if ($k !== null && ctype_digit((string) $k) && (int) $k <= 100) {
            return (int) $k;
        }

        return LegerNilai::pengaturan($ujian->kelas, (int) ($ujian->mapel_id ?? 0))['kkm'];
    }

    public function ujian(Request $request, Ujian $ujian)
    {
        KoreksiUjian::rapikanKedaluwarsa($ujian);
        $ujian->load('kelas', 'asal');

        $kkm = $this->kkm($request, $ujian);
        $h = AnalisisUjian::hitung($ujian, $kkm);

        if ($request->boolean('unduh')) {
            [$judul, $baris] = AnalisisUjian::tabelExport($h);

            return Excel::download(new TabelExport($judul, $baris), 'analisis-' . Str::slug($ujian->judul) . '.xlsx');
        }

        $asal = $ujian->isRemedial() ? $ujian->asal : $ujian;

        return view('analisis.ujian', [
            'ujian'        => $ujian,
            'asal'         => $asal,
            'h'            => $h,
            'kkm'          => $kkm,
            'remedials'    => $asal->remedials()->withCount(['soals', 'pengerjaans'])->get(),
            'banding'      => RemedialUjian::banding($asal, (string) $request->query('aturan', 'kkm'), $kkm),
            'aturan'       => array_key_exists((string) $request->query('aturan'), RemedialUjian::ATURAN)
                ? $request->query('aturan')
                : 'kkm',
            'daftarAturan' => RemedialUjian::ATURAN,
            'siswaKelas'   => $ujian->kelas->siswas()->where('aktif', true)->orderBy('nama')->get(),
        ]);
    }

    public function buatRemedial(Request $request, Ujian $ujian)
    {
        if ($ujian->isRemedial()) {
            return back()->withErrors(['remedial' => 'Buat remedial dari ujian aslinya, bukan dari ujian remedial.']);
        }

        $d = $request->validate([
            'soal_ids'   => 'required|array|min:1',
            'soal_ids.*' => 'integer',
            'siswa_ids'  => 'required|array|min:1',
            'siswa_ids.*'=> 'integer',
            'durasi'     => 'required|integer|min:1|max:300',
        ], [
            'soal_ids.required'  => 'Pilih minimal satu soal untuk paket remedial.',
            'siswa_ids.required' => 'Pilih minimal satu siswa peserta remedial.',
        ]);

        $soalAsal = $ujian->soals()->pluck('soals.id')->all();
        $soalIds = array_values(array_intersect($soalAsal, array_map('intval', $d['soal_ids'])));
        $siswaIds = $ujian->kelas->siswas()->where('aktif', true)->whereIn('id', $d['siswa_ids'])->pluck('id')->all();

        if (! $soalIds || ! $siswaIds) {
            return back()->withErrors(['remedial' => 'Soal atau siswa yang dipilih tidak valid.']);
        }

        $remedial = RemedialUjian::buat($ujian, $soalIds, $siswaIds, (int) $d['durasi']);

        return redirect()->route('ujian.edit', $remedial)
            ->with('ok', 'Ujian remedial dibuat sebagai draf untuk ' . count($siswaIds) . ' siswa. Atur jadwalnya, cek soalnya, lalu buka.');
    }

    public function terapkan(Request $request, Ujian $ujian)
    {
        $d = $request->validate([
            'aturan' => 'required|in:' . implode(',', array_keys(RemedialUjian::ATURAN)),
            'kkm'    => 'required|integer|min:0|max:100',
        ]);

        $hasil = RemedialUjian::terapkan($ujian, $d['aturan'], (int) $d['kkm']);
        if ($hasil['galat']) {
            return back()->withErrors(['terapkan' => $hasil['galat']]);
        }

        return back()->with('ok', 'Nilai remedial diterapkan untuk ' . $hasil['diterapkan'] . ' siswa. Leger ikut berubah.');
    }
}