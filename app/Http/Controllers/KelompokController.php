<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\KelompokSesi;
use App\Services\BagiKelompok;
use App\Support\TemaKelompok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class KelompokController extends Controller
{
    /* ---------- Daftar & buat ---------- */

    public function index(Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $sesis = $kelas->kelompokSesis()->withCount('kelompoks')->latest()->get();

        return view('kelompok.index', compact('kelas', 'sesis'));
    }

    public function store(Request $request, Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $data = $request->validate([
            'nama'        => 'required|string|max:60',
            'berdasarkan' => 'required|in:jumlah,anggota',
            'nilai'       => 'required|integer|min:1|max:40',
            'metode'      => 'required|in:acak,campur_gender,kosong',
            'tema'        => 'required|in:angka,warna,hewan,planet,buah',
        ]);

        $total = $kelas->siswas()->where('aktif', true)->count();

        if ($total === 0) {
            return back()->withErrors(['nama' => 'Kelas ini belum punya siswa aktif.'])->withInput();
        }

        $n = $data['berdasarkan'] === 'jumlah'
            ? (int) $data['nilai']
            : (int) ceil($total / $data['nilai']);
        $n = max(1, min($n, 20, $total));

        $sesi = DB::transaction(function () use ($kelas, $data, $n) {
            $sesi = $kelas->kelompokSesis()->create(['nama' => $data['nama']]);

            for ($i = 0; $i < $n; $i++) {
                $sesi->kelompoks()->create([
                    'nama'   => TemaKelompok::nama($data['tema'], $i),
                    'urutan' => $i,
                ]);
            }

            return $sesi;
        });

        if ($data['metode'] !== 'kosong') {
            BagiKelompok::jalankan($sesi, $data['metode']);
        }

        return redirect()->route('kelompok.edit', $sesi);
    }

    public function destroy(KelompokSesi $sesi)
    {
        $this->milik($sesi);

        $kelasId = $sesi->kelas_id;
        $sesi->delete();

        return redirect()->route('kelompok.index', $kelasId)->with('ok', 'Sesi kelompok dihapus.');
    }

    /* ---------- Editor ---------- */

    public function edit(KelompokSesi $sesi)
    {
        $this->milik($sesi);

        $kelas = $sesi->kelas;
        $siswas = $kelas->siswas()->where('aktif', true)->orderBy('nama')->get()
            ->map(fn ($s) => [
                'id'     => $s->id,
                'nama'   => $s->nama,
                'tampil' => $s->nama_panggilan ?: Str::words($s->nama, 1, ''),
                'gender' => $s->gender,
            ])
            ->values();

        $data = array_merge($this->payload($sesi), [
            'siswas' => $siswas,
            'tema'   => TemaKelompok::daftar(),
            'urls'   => [
                'state'  => route('kelompok.state', $sesi),
                'acak'   => route('kelompok.acak', $sesi),
                'publik' => route('kelompok.publik', $sesi->token),
            ],
        ]);

        return view('kelompok.edit', compact('sesi', 'kelas', 'data'));
    }

    // Simpan keadaan terkini (hasil edit manual)
    public function state(Request $request, KelompokSesi $sesi)
    {
        $this->milik($sesi);

        $v = $request->validate([
            'nama'                          => 'required|string|max:60',
            'nama_lengkap'                  => 'nullable|boolean',
            'kelompoks'                     => 'present|array|max:20',
            'kelompoks.*.id'                => 'nullable|integer',
            'kelompoks.*.nama'              => 'required|string|max:40',
            'kelompoks.*.ketua'             => 'nullable|integer',
            'kelompoks.*.anggota'           => 'present|array|max:60',
            'kelompoks.*.anggota.*.siswa_id' => 'required|integer',
            'kelompoks.*.anggota.*.terkunci' => 'nullable|boolean',
        ]);

        $siswaValid = $sesi->kelas->siswas()->pluck('id')->all();

        DB::transaction(function () use ($sesi, $v, $siswaValid) {
            $sesi->update([
                'nama'         => $v['nama'],
                'nama_lengkap' => (bool) ($v['nama_lengkap'] ?? false),
            ]);

            $idDipakai = [];
            $siswaPakai = [];

            foreach ($v['kelompoks'] as $urutan => $item) {
                $kelompok = ! empty($item['id']) ? $sesi->kelompoks()->find($item['id']) : null;

                if ($kelompok) {
                    $kelompok->update([
                        'nama'   => $item['nama'],
                        'urutan' => $urutan,
                    ]);
                } else {
                    $kelompok = $sesi->kelompoks()->create([
                        'nama'   => $item['nama'],
                        'urutan' => $urutan,
                    ]);
                }

                $idDipakai[] = $kelompok->id;
                $kelompok->anggotas()->delete();

                $idAnggota = [];

                foreach ($item['anggota'] as $a) {
                    $sid = (int) $a['siswa_id'];

                    if (! in_array($sid, $siswaValid) || in_array($sid, $siswaPakai)) {
                        continue; // Tidak valid / sudah ada di kelompok lain
                    }

                    $siswaPakai[] = $sid;
                    $idAnggota[] = $sid;

                    $kelompok->anggotas()->create([
                        'siswa_id' => $sid,
                        'terkunci' => (bool) ($a['terkunci'] ?? false),
                    ]);
                }

                $ketua = $item['ketua'] ?? null;
                $kelompok->update([
                    'ketua_siswa_id' => in_array($ketua, $idAnggota) ? $ketua : null,
                ]);
            }

            $sesi->kelompoks()->whereNotIn('id', $idDipakai)->delete();
        });

        return response()->json($this->payload($sesi->fresh()));
    }

    public function acak(Request $request, KelompokSesi $sesi)
    {
        $this->milik($sesi);

        $request->validate([
            'metode' => 'required|in:acak,campur_gender',
        ]);

        try {
            BagiKelompok::jalankan(
                $sesi,
                $request->input('metode'),
                $request->boolean('hindari_ulang', true),
                $request->boolean('acak_ketua', true)
            );
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json($this->payload($sesi->fresh()));
    }

    /* ---------- Halaman publik (tanpa login) ---------- */

    public function publik(string $token)
    {
        $sesi = KelompokSesi::where('token', $token)->firstOrFail();

        $nama = $sesi->kelas->siswas()->get()->mapWithKeys(fn ($s) => [
            $s->id => $sesi->nama_lengkap ? $s->nama : ($s->nama_panggilan ?: Str::words($s->nama, 1, '')),
        ]);

        $kelompoks = $sesi->kelompoks()->with('anggotas')->orderBy('urutan')->get()
            ->map(fn ($k) => [
                'nama'    => $k->nama,
                'ketua'   => $k->ketua_siswa_id ? ($nama[$k->ketua_siswa_id] ?? null) : null,
                'anggota' => $k->anggotas->map(fn ($a) => $nama[$a->siswa_id] ?? '')->filter()->values(),
            ])
            ->values();

        $data = [
            'sesi' => [
                'nama'  => $sesi->nama,
                'kelas' => $sesi->kelas->nama,
            ],
            'kelompoks' => $kelompoks,
        ];

        return view('kelompok.publik', compact('sesi', 'data'));
    }

    /* ---------- Bantu ---------- */

    private function payload(KelompokSesi $sesi): array
    {
        return [
            'sesi' => [
                'nama'         => $sesi->nama,
                'nama_lengkap' => (bool) $sesi->nama_lengkap,
            ],
            'kelompoks' => $sesi->kelompoks()->with('anggotas')->orderBy('urutan')->get()
                ->map(fn ($k) => [
                    'id'      => $k->id,
                    'nama'    => $k->nama,
                    'ketua'   => $k->ketua_siswa_id,
                    'anggota' => $k->anggotas->map(fn ($a) => [
                        'siswa_id' => $a->siswa_id,
                        'terkunci' => (bool) $a->terkunci,
                    ])->values(),
                ])
                ->values(),
        ];
    }

    private function milik(KelompokSesi $sesi): void
    {
        abort_unless($sesi->kelas->guru_id === auth()->id(), 403);
    }

    private function milikKelas(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}