<?php

namespace App\Http\Controllers;

use App\Exports\TabelExport;
use App\Models\Kelas;
use App\Models\LegerPengaturan;
use App\Models\Mapel;
use App\Models\Penilaian;
use App\Services\LegerNilai;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class LegerController extends Controller
{
    /** Mapel yang punya penilaian di kelas ini: [id => nama], id 0 = penilaian tanpa mapel. */
    private function daftarMapel(Kelas $kelas): array
    {
        $ids = Penilaian::where('kelas_id', $kelas->id)->distinct()->pluck('mapel_id')->all();
        $daftar = [];
        $nama = Mapel::where('guru_id', $kelas->guru_id)
            ->whereIn('id', array_filter($ids))
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();

        foreach ($nama as $id => $n) {
            $daftar[(int) $id] = $n;
        }

        if (in_array(null, $ids, true)) {
            $daftar[0] = 'Tanpa mapel';
        }

        return $daftar;
    }

    /** Mapel terpilih dari ?mapel=; kalau tidak valid, pakai yang pertama. */
    private function mapelDipilih(Request $r, array $daftar): int
    {
        $m = $r->input('mapel');
        if ($m !== null && array_key_exists((int) $m, $daftar)) {
            return (int) $m;
        }

        return $daftar ? (int) array_key_first($daftar) : 0;
    }

    private function data(Kelas $kelas, Request $r): array
    {
        $daftar = $this->daftarMapel($kelas);
        $mapel = $this->mapelDipilih($r, $daftar);
        $h = LegerNilai::hitung($kelas, $mapel);

        return [$daftar, $mapel, $h];
    }

    public function index(Request $request, Kelas $kelas)
    {
        [$daftar, $mapel, $h] = $this->data($kelas, $request);

        // kelas+mapel ini memakai rekap per bab (Fase 9A-2)
        if (\App\Services\LegerBabNilai::modeBab($kelas, $mapel)) {
            return redirect()->route('leger.bab', [$kelas, 'mapel' => $mapel]);
        }

        return view('leger.index', [
            'kelas'       => $kelas,
            'daftarMapel' => $daftar,
            'mapel'       => $mapel,
            'h'           => $h,
            'asal'        => LegerNilai::asalMap($kelas, $h['penilaians']),
            'belum'       => LegerNilai::belumMasuk($kelas, $mapel),
            'kategori'    => LegerNilai::KATEGORI,
        ]);
    }

    public function simpan(Request $request, Kelas $kelas)
    {
        $data = $request->validate([
            'mapel_id'    => ['required', 'integer', 'min:0'],
            'kkm'         => ['required', 'integer', 'min:0', 'max:100'],
            'kosong'      => ['required', 'in:lewati,nol'],
            'bobot'       => ['required', 'array'],
            'bobot.*'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'kategori'    => ['nullable', 'array'],
            'kategori.*'  => ['nullable', 'in:' . implode(',', array_keys(LegerNilai::KATEGORI))],
        ], [
            'bobot.*.integer' => 'Bobot harus angka bulat 0 sampai 100.',
            'bobot.*.max'     => 'Bobot paling besar 100.',
        ]);

        $mapelId = (int) $data['mapel_id'];
        if ($mapelId > 0) {
            abort_unless(Mapel::where('guru_id', $kelas->guru_id)->whereKey($mapelId)->exists(), 403);
        }

        $bobot = [];
        foreach (array_keys(LegerNilai::KATEGORI) as $k) {
            $bobot[$k] = (int) ($data['bobot'][$k] ?? 0);
        }

        if (array_sum($bobot) <= 0) {
            return back()->withErrors(['bobot' => 'Minimal satu kategori harus berbobot lebih dari 0.'])->withInput();
        }

        LegerPengaturan::updateOrCreate(
            ['kelas_id' => $kelas->id, 'mapel_id' => $mapelId],
            ['kkm' => $data['kkm'], 'kosong' => $data['kosong'], 'bobot' => $bobot]
        );

        // Kategori per penilaian: hanya penilaian milik kelas ini yang boleh diubah
        foreach ((array) ($data['kategori'] ?? []) as $pid => $kat) {
            Penilaian::where('kelas_id', $kelas->id)->whereKey((int) $pid)->update(['kategori' => $kat ?: null]);
        }

        return redirect()->route('leger.index', [$kelas, 'mapel' => $mapelId])
            ->with('ok', 'Pengaturan leger disimpan.');
    }

    public function ekspor(Request $request, Kelas $kelas)
    {
        [$daftar, $mapel, $h] = $this->data($kelas, $request);
        [$judul, $baris] = LegerNilai::tabelExport($kelas, $h);
        $nama = 'leger-' . Str::slug($kelas->nama . ' ' . ($daftar[$mapel] ?? 'nilai')) . '.xlsx';

        return Excel::download(new TabelExport($judul, $baris), $nama);
    }

    public function cetak(Request $request, Kelas $kelas)
    {
        [$daftar, $mapel, $h] = $this->data($kelas, $request);

        return view('leger.cetak', [
            'kelas'      => $kelas,
            'namaMapel'  => $daftar[$mapel] ?? '-',
            'h'          => $h,
            'kategori'   => LegerNilai::KATEGORI,
            'guru'       => $request->user()->name,
        ]);
    }
}