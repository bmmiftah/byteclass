<?php

namespace App\Http\Controllers;

use App\Models\Denah;
use App\Models\DenahAturan;
use App\Models\DenahRiwayat;
use App\Models\Kelas;
use App\Services\AcakDenah;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class DenahController extends Controller
{
    /* ---------- Daftar & buat ---------- */

    public function index(Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $denahs = $kelas->denahs()->withCount('mejas')->latest()->get();

        return view('denah.index', compact('kelas', 'denahs'));
    }

    public function store(Request $request, Kelas $kelas)
    {
        $this->milikKelas($kelas);

        $data = $request->validate([
            'nama'     => 'required|string|max:60',
            'template' => 'required|in:baris,pasangan,u,kosong',
            'lebar'    => 'required|integer|min:3|max:16',
            'tinggi'   => 'required|integer|min:3|max:12',
        ]);

        $denah = DB::transaction(function () use ($kelas, $data) {
            $denah = $kelas->denahs()->create(Arr::only($data, ['nama', 'lebar', 'tinggi']));

            foreach ($this->posisiTemplate($data['template'], $data['lebar'], $data['tinggi']) as [$x, $y]) {
                $denah->mejas()->create(['x' => $x, 'y' => $y]);
            }

            return $denah;
        });

        return redirect()->route('denah.edit', $denah);
    }

    public function destroy(Denah $denah)
    {
        $this->milik($denah);

        $kelasId = $denah->kelas_id;
        $denah->delete();

        return redirect()->route('denah.index', $kelasId)->with('ok', 'Denah dihapus.');
    }

    /* ---------- Editor ---------- */

    public function edit(Denah $denah)
    {
        $this->milik($denah);

        $kelas = $denah->kelas;
        $siswas = $kelas->siswas()->where('aktif', true)->orderBy('nama')->get()
            ->map(fn ($s) => [
                'id'     => $s->id,
                'nama'   => $s->nama,
                'tampil' => $s->nama_panggilan ?: Str::words($s->nama, 1, ''),
            ])
            ->values();

        $data = array_merge($this->payload($denah), [
            'siswas' => $siswas,
            'urls'   => [
                'layout'      => route('denah.layout', $denah),
                'acak'        => route('denah.acak', $denah),
                'aturan'      => route('denah.aturan.store', $denah),
                'aturanHapus' => route('denah.aturan.destroy', '__ID__'),
                'pulihkan'    => route('denah.pulihkan', '__ID__'),
                'publik'      => route('denah.publik', $denah->token),
            ],
        ]);

        return view('denah.edit', compact('denah', 'kelas', 'data'));
    }

    // Simpan layout (nama, ukuran, daftar meja)
    public function layout(Request $request, Denah $denah)
    {
        $this->milik($denah);

        $lebar = (int) $request->input('lebar', $denah->lebar);
        $tinggi = (int) $request->input('tinggi', $denah->tinggi);

        $v = $request->validate([
            'nama'               => 'required|string|max:60',
            'lebar'              => 'required|integer|min:3|max:16',
            'tinggi'             => 'required|integer|min:3|max:12',
            'nama_lengkap'       => 'nullable|boolean',
            'mejas'              => 'present|array|max:200',
            'mejas.*.id'         => 'nullable|integer',
            'mejas.*.x'          => 'required|integer|min:0|max:' . ($lebar - 1),
            'mejas.*.y'          => 'required|integer|min:0|max:' . ($tinggi - 1),
            'mejas.*.terkunci'   => 'nullable|boolean',
            'mejas.*.siswa_id'   => 'nullable|integer',
        ]);

        $siswaValid = $denah->kelas->siswas()->pluck('id')->all();

        DB::transaction(function () use ($denah, $v, $siswaValid) {
            $denah->update([
                'nama'         => $v['nama'],
                'lebar'        => $v['lebar'],
                'tinggi'       => $v['tinggi'],
                'nama_lengkap' => (bool) ($v['nama_lengkap'] ?? false),
            ]);

            $idDipakai = [];
            $terisi = [];
            $siswaPakai = [];

            foreach ($v['mejas'] as $m) {
                $kunci = $m['x'] . ',' . $m['y'];
                if (isset($terisi[$kunci])) {
                    continue; // satu kotak hanya boleh satu meja
                }
                $terisi[$kunci] = true;

                $siswa = $m['siswa_id'] ?? null;
                if ($siswa && (! in_array($siswa, $siswaValid) || in_array($siswa, $siswaPakai))) {
                    $siswa = null; // siswa tidak valid atau sudah duduk di meja lain
                }
                if ($siswa) {
                    $siswaPakai[] = $siswa;
                }

                $atribut = [
                    'x'        => $m['x'],
                    'y'        => $m['y'],
                    'terkunci' => (bool) ($m['terkunci'] ?? false),
                    'siswa_id' => $siswa,
                ];

                $meja = ! empty($m['id']) ? $denah->mejas()->find($m['id']) : null;
                if ($meja) {
                    $meja->update($atribut);
                } else {
                    $meja = $denah->mejas()->create($atribut);
                }

                $idDipakai[] = $meja->id;
            }

            $denah->mejas()->whereNotIn('id', $idDipakai)->delete();
        });

        return response()->json($this->payload($denah->fresh()));
    }

    public function acak(Request $request, Denah $denah)
    {
        $this->milik($denah);

        try {
            AcakDenah::jalankan($denah, $request->boolean('hindari_sama', true));
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json($this->payload($denah->fresh()));
    }

    /* ---------- Aturan pisah ---------- */

    public function aturanTambah(Request $request, Denah $denah)
    {
        $this->milik($denah);

        $valid = $denah->kelas->siswas()->pluck('id')->all();

        $v = $request->validate([
            'siswa_a' => ['required', 'integer', 'different:siswa_b', Rule::in($valid)],
            'siswa_b' => ['required', 'integer', Rule::in($valid)],
        ]);

        $sudahAda = $denah->aturan()->where(function ($q) use ($v) {
            $q->where(fn ($w) => $w->where('siswa_a', $v['siswa_a'])->where('siswa_b', $v['siswa_b']))
                ->orWhere(fn ($w) => $w->where('siswa_a', $v['siswa_b'])->where('siswa_b', $v['siswa_a']));
        })->exists();

        if (! $sudahAda) {
            $denah->aturan()->create($v);
        }

        return response()->json($this->payload($denah->fresh()));
    }

    public function aturanHapus(DenahAturan $aturan)
    {
        $denah = $aturan->denah;
        $this->milik($denah);

        $aturan->delete();

        return response()->json($this->payload($denah->fresh()));
    }

    /* ---------- Riwayat ---------- */

    public function pulihkan(DenahRiwayat $riwayat)
    {
        $denah = $riwayat->denah;
        $this->milik($denah);

        $peta = collect($riwayat->snapshot)->pluck('siswa_id', 'id');

        DB::transaction(function () use ($denah, $peta) {
            foreach ($denah->mejas as $meja) {
                $meja->update(['siswa_id' => $peta[$meja->id] ?? null]);
            }
        });

        return response()->json($this->payload($denah->fresh()));
    }

    /* ---------- Halaman publik (tanpa login) ---------- */

    public function publik(string $token)
    {
        $denah = Denah::where('token', $token)->firstOrFail();

        $siswa = $denah->kelas->siswas()->get()->mapWithKeys(fn ($s) => [
            $s->id => $denah->nama_lengkap ? $s->nama : ($s->nama_panggilan ?: Str::words($s->nama, 1, '')),
        ])->toArray();

        $data = [
            'denah' => [
                'nama'   => $denah->nama,
                'kelas'  => $denah->kelas->nama,
                'lebar'  => $denah->lebar,
                'tinggi' => $denah->tinggi,
            ],
            'mejas' => $denah->mejas()->get(['id', 'x', 'y', 'siswa_id'])->values(),
            'siswa' => $siswa,
        ];

        return view('denah.publik', compact('denah', 'data'));
    }

    /* ---------- Bantu ---------- */

    private function payload(Denah $denah): array
    {
        return [
            'denah' => [
                'nama'         => $denah->nama,
                'lebar'        => $denah->lebar,
                'tinggi'       => $denah->tinggi,
                'nama_lengkap' => (bool) $denah->nama_lengkap,
            ],
            'mejas' => $denah->mejas()->get(['id', 'x', 'y', 'terkunci', 'siswa_id'])
                ->map(fn ($m) => [
                    'id'       => $m->id,
                    'x'        => $m->x,
                    'y'        => $m->y,
                    'terkunci' => (bool) $m->terkunci,
                    'siswa_id' => $m->siswa_id,
                ])->values(),
            'aturan'  => $denah->aturan()->get(['id', 'siswa_a', 'siswa_b'])->values(),
            'riwayat' => $denah->riwayat()->latest()->take(10)->get(['id', 'created_at'])
                ->map(fn ($r) => [
                    'id'    => $r->id,
                    'waktu' => $r->created_at->translatedFormat('d M Y H:i'),
                ])
                ->values(),
        ];
    }

    private function posisiTemplate(string $template, int $lebar, int $tinggi): array
    {
        $posisi = [];

        for ($y = 0; $y < $tinggi; $y++) {
            for ($x = 0; $x < $lebar; $x++) {
                $ada = match ($template) {
                    'baris'    => true,
                    'pasangan' => $x % 3 !== 2, // dua meja, satu lorong
                    'u'        => $x === 0 || $x === $lebar - 1 || $y === $tinggi - 1,
                    default    => false,
                };

                if ($ada) {
                    $posisi[] = [$x, $y];
                }
            }
        }

        return $posisi;
    }

    private function milik(Denah $denah): void
    {
        abort_unless($denah->kelas->guru_id === auth()->id(), 403);
    }

    private function milikKelas(Kelas $kelas): void
    {
        abort_unless($kelas->guru_id === auth()->id(), 403);
    }
}