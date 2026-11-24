<?php

namespace App\Http\Controllers;

use App\Exports\TabelExport;
use App\Models\Kelas;
use App\Models\LegerBab;
use App\Models\LegerPengaturan;
use App\Models\LegerSlot;
use App\Models\Mapel;
use App\Models\Penilaian;
use App\Services\LegerBabNilai;
use App\Services\LegerNilai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class LegerBabController extends Controller
{
    /** Mapel yang punya penilaian atau bab di kelas ini: [id => nama], id 0 = tanpa mapel. */
    private function daftarMapel(Kelas $kelas): array
    {
        $ids = Penilaian::where('kelas_id', $kelas->id)->distinct()->pluck('mapel_id')
            ->merge(LegerBab::where('kelas_id', $kelas->id)->distinct()->pluck('mapel_id'))
            ->map(fn ($v) => (int) $v)->unique()->all();

        $nama = Mapel::where('guru_id', $kelas->guru_id)
            ->whereIn('id', array_filter($ids))
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();

        $daftar = [];
        foreach ($nama as $id => $n) {
            $daftar[(int) $id] = $n;
        }
        if (in_array(0, $ids, true)) {
            $daftar[0] = 'Tanpa mapel';
        }

        return $daftar;
    }

    private function mapelDipilih(Request $r, array $daftar): int
    {
        $m = $r->input('mapel');
        if ($m !== null && array_key_exists((int) $m, $daftar)) {
            return (int) $m;
        }

        return $daftar ? (int) array_key_first($daftar) : 0;
    }

    private function cekMapel(Kelas $kelas, int $mapelId): void
    {
        if ($mapelId > 0) {
            abort_unless(Mapel::where('guru_id', $kelas->guru_id)->whereKey($mapelId)->exists(), 403);
        }
    }

    public function index(Request $request, Kelas $kelas)
    {
        $daftar = $this->daftarMapel($kelas);
        $mapel = $this->mapelDipilih($request, $daftar);
        $h = LegerBabNilai::hitung($kelas, $mapel);

        return view('leger.bab', [
            'kelas'       => $kelas,
            'daftarMapel' => $daftar,
            'mapel'       => $mapel,
            'h'           => $h,
            'aspek'       => LegerBabNilai::ASPEK,
        ]);
    }

    public function struktur(Request $request, Kelas $kelas)
    {
        $daftar = $this->daftarMapel($kelas);
        $mapel = $this->mapelDipilih($request, $daftar);
        $penilaians = LegerNilai::penilaians($kelas, $mapel);

        $dipakai = [];
        $babs = LegerBabNilai::babs($kelas, $mapel);
        foreach ($babs as $b) {
            foreach ($b->slots as $s) {
                if ($s->penilaian_id) {
                    $dipakai[$s->penilaian_id] = $b->nama . ' / ' . $s->nama;
                }
            }
        }

        $set = LegerBabNilai::pengaturan($kelas, $mapel);
        foreach (['pts_id' => 'PTS', 'pas_id' => 'PAS'] as $k => $label) {
            if ($set[$k]) {
                $dipakai[$set[$k]] = $label;
            }
        }

        return view('leger.struktur', [
            'kelas'      => $kelas,
            'daftarMapel'=> $daftar,
            'mapel'      => $mapel,
            'babs'       => $babs,
            'set'        => $set,
            'penilaians' => $penilaians,
            'asal'       => LegerNilai::asalMap($kelas, $penilaians),
            'dipakai'    => $dipakai,
            'aspek'      => LegerBabNilai::ASPEK,
        ]);
    }

    /** Ganti mode rekap (kategori Fase 9A atau per bab). Tidak mengubah data penilaian. */
    public function mode(Request $request, Kelas $kelas)
    {
        $d = $request->validate([
            'mapel_id' => 'required|integer|min:0',
            'mode'     => 'required|in:kategori,bab',
        ]);
        $mapelId = (int) $d['mapel_id'];
        $this->cekMapel($kelas, $mapelId);

        $p = LegerPengaturan::firstOrNew(['kelas_id' => $kelas->id, 'mapel_id' => $mapelId]);
        if (! $p->exists) {
            $set = LegerNilai::pengaturan($kelas, $mapelId);
            $p->kkm = $set['kkm'];
            $p->kosong = $set['kosong'];
            $p->bobot = $set['bobot'];
        }
        $p->mode = $d['mode'];
        $p->save();

        return redirect()
            ->route($d['mode'] === 'bab' ? 'leger.bab' : 'leger.index', [$kelas, 'mapel' => $mapelId])
            ->with('ok', $d['mode'] === 'bab' ? 'Mode rekap per bab dinyalakan.' : 'Kembali ke mode kategori.');
    }

    public function babTambah(Request $request, Kelas $kelas)
    {
        $d = $request->validate([
            'mapel_id' => 'required|integer|min:0',
            'nama'     => 'required|string|max:150',
        ]);
        $mapelId = (int) $d['mapel_id'];
        $this->cekMapel($kelas, $mapelId);

        $urutan = (int) LegerBab::where('kelas_id', $kelas->id)->where('mapel_id', $mapelId)->max('urutan') + 1;
        LegerBab::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapelId,
            'nama'     => $d['nama'],
            'urutan'   => $urutan,
        ]);

        return redirect()
            ->route('leger.struktur', [$kelas, 'mapel' => $mapelId])
            ->with('ok', 'Bab ditambahkan.');
    }

    /** Penilaian boleh dipakai slot kalau milik kelas+mapel ini dan belum dipakai tempat lain. */
    private function penilaianBoleh(LegerBab $bab, ?int $id, array $dipakaiLain): bool
    {
        if ($id === null) {
            return true;
        }

        $ada = Penilaian::where('kelas_id', $bab->kelas_id)->whereKey($id)
            ->when($bab->mapel_id > 0, fn ($q) => $q->where('mapel_id', $bab->mapel_id), fn ($q) => $q->whereNull('mapel_id'))
            ->exists();

        return $ada && ! in_array($id, $dipakaiLain, true);
    }

    public function babSimpan(Request $request, LegerBab $bab)
    {
        $d = $request->validate([
            'nama'                   => 'required|string|max:150',
            'slot'                   => 'nullable|array',
            'slot.*.nama'            => 'nullable|string|max:80',
            'slot.*.bobot'           => 'nullable|integer|min:1|max:100',
            'slot.*.penilaian_id'    => 'nullable|integer',
            'slot.*.hapus'           => 'nullable',
            'baru'                   => 'nullable|array',
            'baru.*.*.nama'          => 'nullable|string|max:80',
            'baru.*.*.bobot'         => 'nullable|integer|min:1|max:100',
            'baru.*.*.penilaian_id'  => 'nullable|integer',
        ], [
            'slot.*.bobot.min'      => 'Bobot slot minimal 1.',
            'slot.*.bobot.max'      => 'Bobot slot paling besar 100.',
            'baru.*.*.bobot.min'    => 'Bobot slot minimal 1.',
        ]);

        // Penilaian yang sudah dipakai di luar bab ini (bab lain, PTS, PAS)
        $lain = LegerSlot::whereHas('bab', fn ($q) => $q->where('kelas_id', $bab->kelas_id)
            ->where('mapel_id', $bab->mapel_id)
            ->where('id', '!=', $bab->id))
            ->whereNotNull('penilaian_id')
            ->pluck('penilaian_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $pengaturan = LegerPengaturan::where('kelas_id', $bab->kelas_id)->where('mapel_id', $bab->mapel_id)->first();
        foreach ([$pengaturan?->pts_penilaian_id, $pengaturan?->pas_penilaian_id] as $x) {
            if ($x) {
                $lain[] = (int) $x;
            }
        }

        // Kumpulkan semua pilihan di form ini dan cek tidak ada yang ganda
        $pilihan = [];
        foreach ((array) ($d['slot'] ?? []) as $sid => $row) {
            if (! empty($row['hapus'])) {
                continue;
            }
            if (($row['penilaian_id'] ?? null) !== null && $row['penilaian_id'] !== '') {
                $pilihan[] = (int) $row['penilaian_id'];
            }
        }
        foreach ((array) ($d['baru'] ?? []) as $rows) {
            foreach ($rows as $row) {
                if (trim((string) ($row['nama'] ?? '')) !== '' && ($row['penilaian_id'] ?? null) !== null && $row['penilaian_id'] !== '') {
                    $pilihan[] = (int) $row['penilaian_id'];
                }
            }
        }

        if (count($pilihan) !== count(array_unique($pilihan))) {
            return back()->withErrors(['slot' => 'Satu penilaian hanya boleh mengisi satu slot.'])->withInput();
        }

        foreach ($pilihan as $pid) {
            if (! $this->penilaianBoleh($bab, $pid, $lain)) {
                return back()->withErrors(['slot' => 'Ada penilaian yang bukan milik kelas dan mapel ini, atau sudah dipakai slot lain, PTS, atau PAS.'])->withInput();
            }
        }

        DB::transaction(function () use ($bab, $d, $request) {
            $bab->update([
                'nama'         => $d['nama'],
                'pengetahuan'  => $request->boolean('pengetahuan'),
                'keterampilan' => $request->boolean('keterampilan'),
            ]);

            $milik = $bab->slots()->get()->keyBy('id');
            $urut = 0;
            foreach ((array) ($d['slot'] ?? []) as $sid => $row) {
                $slot = $milik->get((int) $sid);
                if (! $slot) {
                    continue;
                }
                if (! empty($row['hapus'])) {
                    $slot->delete();
                    continue;
                }
                $slot->update([
                    'nama'         => trim((string) ($row['nama'] ?? '')) !== '' ? trim($row['nama']) : $slot->nama,
                    'bobot'        => (int) ($row['bobot'] ?? $slot->bobot),
                    'penilaian_id' => ($row['penilaian_id'] ?? '') === '' ? null : (int) $row['penilaian_id'],
                    'urutan'       => $urut++,
                ]);
            }

            $urut = (int) $bab->slots()->max('urutan') + 1;
            foreach (['pengetahuan', 'keterampilan'] as $aspek) {
                foreach ((array) ($d['baru'][$aspek] ?? []) as $row) {
                    $nama = trim((string) ($row['nama'] ?? ''));
                    if ($nama === '') {
                        continue;
                    }
                    LegerSlot::create([
                        'leger_bab_id' => $bab->id,
                        'aspek'        => $aspek,
                        'nama'         => $nama,
                        'bobot'        => (int) ($row['bobot'] ?? 100) ?: 100,
                        'penilaian_id' => ($row['penilaian_id'] ?? '') === '' ? null : (int) $row['penilaian_id'],
                        'urutan'       => $urut++,
                    ]);
                }
            }
        });

        return redirect()
            ->route('leger.struktur', [$bab->kelas_id, 'mapel' => $bab->mapel_id])
            ->with('ok', 'Bab "' . $bab->nama . '" disimpan.');
    }

    public function babHapus(LegerBab $bab)
    {
        $kelasId = $bab->kelas_id;
        $mapelId = $bab->mapel_id;
        $bab->delete();

        return redirect()
            ->route('leger.struktur', [$kelasId, 'mapel' => $mapelId])
            ->with('ok', 'Bab dihapus. Nilai di Penilaian tidak terhapus.');
    }

    /** Salin susunan slot (nama, bobot, aspek) ke bab lain; penilaiannya tidak ikut disalin. */
    public function babSalin(Request $request, LegerBab $bab)
    {
        $d = $request->validate([
            'target'   => 'required|array|min:1',
            'target.*' => 'integer',
        ], [
            'target.required' => 'Pilih minimal satu bab tujuan.',
        ]);

        $tujuan = LegerBab::where('kelas_id', $bab->kelas_id)
            ->where('mapel_id', $bab->mapel_id)
            ->where('id', '!=', $bab->id)
            ->whereIn('id', $d['target'])
            ->get();

        $sumber = $bab->slots()->get();

        DB::transaction(function () use ($tujuan, $sumber, $bab) {
            foreach ($tujuan as $t) {
                $t->slots()->delete();
                $t->update([
                    'pengetahuan'  => $bab->pengetahuan,
                    'keterampilan' => $bab->keterampilan,
                ]);
                foreach ($sumber as $i => $s) {
                    LegerSlot::create([
                        'leger_bab_id' => $t->id,
                        'aspek'        => $s->aspek,
                        'nama'         => $s->nama,
                        'bobot'        => $s->bobot,
                        'urutan'       => $i,
                    ]);
                }
            }
        });

        return redirect()
            ->route('leger.struktur', [$bab->kelas_id, 'mapel' => $bab->mapel_id])
            ->with('ok', 'Susunan slot disalin ke ' . $tujuan->count() . ' bab. Pilih penilaian untuk tiap slotnya.');
    }

    public function ptsPas(Request $request, Kelas $kelas)
    {
        $d = $request->validate([
            'mapel_id'         => 'required|integer|min:0',
            'kkm'              => 'required|integer|min:0|max:100',
            'pts_penilaian_id' => 'nullable|integer',
            'pas_penilaian_id' => 'nullable|integer',
            'bobot_nkd'        => 'required|integer|min:0|max:100',
            'bobot_pts'        => 'required|integer|min:0|max:100',
            'bobot_pas'        => 'required|integer|min:0|max:100',
        ]);

        $mapelId = (int) $d['mapel_id'];
        $this->cekMapel($kelas, $mapelId);

        $pts = ($d['pts_penilaian_id'] ?? null) ? (int) $d['pts_penilaian_id'] : null;
        $pas = ($d['pas_penilaian_id'] ?? null) ? (int) $d['pas_penilaian_id'] : null;

        if ($pts && $pts === $pas) {
            return back()->withErrors(['pts' => 'PTS dan PAS tidak boleh memakai penilaian yang sama.'])->withInput();
        }
        if ($d['bobot_nkd'] + $d['bobot_pts'] + $d['bobot_pas'] <= 0) {
            return back()->withErrors(['pts' => 'Minimal satu bobot harus lebih dari 0.'])->withInput();
        }

        $dipakaiSlot = LegerSlot::whereHas('bab', fn ($q) => $q->where('kelas_id', $kelas->id)->where('mapel_id', $mapelId))
            ->whereNotNull('penilaian_id')
            ->pluck('penilaian_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        foreach ([$pts, $pas] as $id) {
            if ($id === null) {
                continue;
            }
            $ok = Penilaian::where('kelas_id', $kelas->id)->whereKey($id)
                ->when($mapelId > 0, fn ($q) => $q->where('mapel_id', $mapelId), fn ($q) => $q->whereNull('mapel_id'))
                ->exists();

            if (! $ok || in_array($id, $dipakaiSlot, true)) {
                return back()->withErrors(['pts' => 'PTS atau PAS harus penilaian kelas dan mapel ini yang belum dipakai slot bab.'])->withInput();
            }
        }

        $p = LegerPengaturan::firstOrNew(['kelas_id' => $kelas->id, 'mapel_id' => $mapelId]);
        if (! $p->exists) {
            $set = LegerNilai::pengaturan($kelas, $mapelId);
            $p->kosong = $set['kosong'];
            $p->bobot = $set['bobot'];
        }

        $p->fill([
            'kkm'              => $d['kkm'],
            'pts_penilaian_id' => $pts,
            'pas_penilaian_id' => $pas,
            'bobot_nkd'        => $d['bobot_nkd'],
            'bobot_pts'        => $d['bobot_pts'],
            'bobot_pas'        => $d['bobot_pas'],
        ])->save();

        return redirect()
            ->route('leger.struktur', [$kelas, 'mapel' => $mapelId])
            ->with('ok', 'Pengaturan PTS, PAS, dan KKM disimpan.');
    }

    public function ekspor(Request $request, Kelas $kelas)
    {
        $daftar = $this->daftarMapel($kelas);
        $mapel = $this->mapelDipilih($request, $daftar);
        $h = LegerBabNilai::hitung($kelas, $mapel);
        [$judul, $baris] = LegerBabNilai::tabelExport($h);

        return Excel::download(
            new TabelExport($judul, $baris),
            'leger-bab-' . Str::slug($kelas->nama . ' ' . ($daftar[$mapel] ?? 'nilai')) . '.xlsx'
        );
    }

    public function cetak(Request $request, Kelas $kelas)
    {
        $daftar = $this->daftarMapel($kelas);
        $mapel = $this->mapelDipilih($request, $daftar);

        return view('leger.bab-cetak', [
            'kelas'      => $kelas,
            'namaMapel'  => $daftar[$mapel] ?? '-',
            'h'          => LegerBabNilai::hitung($kelas, $mapel),
            'aspek'      => LegerBabNilai::ASPEK,
            'guru'       => $request->user()->name,
        ]);
    }
}