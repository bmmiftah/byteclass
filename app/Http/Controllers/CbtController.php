<?php

namespace App\Http\Controllers;

use App\Models\AkunSiswa;
use App\Models\Jawaban;
use App\Models\Pengerjaan;
use App\Models\Soal;
use App\Models\Ujian;
use App\Services\KoreksiUjian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CbtController extends Controller
{
    /* ---------- Masuk dan keluar ---------- */

    public function masukForm(Request $request)
    {
        return view('cbt.masuk', ['kode' => $request->query('kode')]);
    }

    public function masuk(Request $request)
    {
        $request->validate(['kode' => 'required|string|max:20']);

        $akun = AkunSiswa::where('kode', AkunSiswa::bersihkan($request->input('kode')))->first();

        if (! $akun || ! $akun->siswa || ! $akun->siswa->aktif) {
            return back()->withInput()->withErrors([
                'kode' => 'Kode tidak dikenal. Periksa lagi kartu loginmu.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('akun_id', $akun->id);
        $akun->forceFill(['terakhir_masuk' => now()])->save();

        return redirect()->route('cbt.daftar');
    }

    public function keluar(Request $request)
    {
        $request->session()->forget('akun_id');

        return redirect()->route('cbt.masuk');
    }

    /* ---------- Daftar ujian ---------- */

    public function daftar(Request $request)
    {
        $akun = $this->akun($request);
        $siswa = $akun->siswa;

        $ujians = Ujian::where('kelas_id', $siswa->kelas_id)
            ->whereIn('status', ['buka', 'tutup'])
            ->orderByDesc('id')
            ->get();

        $ps = Pengerjaan::where('siswa_id', $siswa->id)
            ->whereIn('ujian_id', $ujians->pluck('id'))
            ->get()
            ->keyBy('ujian_id');

        // Ujian ditutup hanya terlihat jika siswa pernah mengerjakannya
        $ujians = $ujians->filter(fn ($u) => $u->status === 'buka' || isset($ps[$u->id]))->values();

        return view('cbt.daftar', compact('akun', 'siswa', 'ujians', 'ps'));
    }

    /* ---------- Mulai ---------- */

    public function tokenForm(Request $request, Ujian $ujian)
    {
        $this->bolehLihat($request, $ujian);

        if ($p = $this->pengerjaan($request, $ujian)) {
            return redirect()->route($p->status === 'selesai' ? 'cbt.hasil' : 'cbt.kerjakan', $ujian);
        }

        return view('cbt.token', compact('ujian'));
    }

    public function mulai(Request $request, Ujian $ujian)
    {
        $this->bolehLihat($request, $ujian);

        if ($p = $this->pengerjaan($request, $ujian)) {
            return redirect()->route($p->status === 'selesai' ? 'cbt.hasil' : 'cbt.kerjakan', $ujian);
        }

        $request->validate(['token' => 'required|string|max:20']);

        if (! $ujian->bisaMulai()) {
            return back()->withErrors([
                'token' => 'Ujian belum dibuka atau sudah ditutup.',
            ]);
        }

        if (! hash_equals(strtoupper($ujian->token), AkunSiswa::bersihkan($request->input('token')))) {
            return back()->withErrors([
                'token' => 'Token salah. Tanyakan ke gurumu.',
            ]);
        }

        if ($ujian->soals()->count() === 0) {
            return back()->withErrors([
                'token' => 'Ujian ini belum punya soal.',
            ]);
        }

        [$urutan, $opsi] = $ujian->susunUrutan();

        $batas = now()->addMinutes($ujian->durasi);
        if ($ujian->tutup_pada && $ujian->tutup_pada->lt($batas)) {
            $batas = $ujian->tutup_pada->copy();
        }

        Pengerjaan::firstOrCreate(
            ['ujian_id' => $ujian->id, 'siswa_id' => $this->akun($request)->siswa_id],
            [
                'mulai_pada' => now(),
                'batas_pada' => $batas,
                'status' => 'berjalan',
                'urutan_soal' => $urutan,
                'urutan_opsi' => $opsi,
            ]
        );

        return redirect()->route('cbt.kerjakan', $ujian);
    }

    /* ---------- Mengerjakan ---------- */

    public function kerjakan(Request $request, Ujian $ujian)
    {
        $this->bolehLihat($request, $ujian);

        $p = $this->pengerjaan($request, $ujian);
        if (! $p) {
            return redirect()->route('cbt.token', $ujian);
        }

        if ($p->status === 'selesai') {
            return redirect()->route('cbt.hasil', $ujian);
        }

        if (now()->gte($p->batas_pada)) {
            KoreksiUjian::selesaikan($p);

            return redirect()->route('cbt.hasil', $ujian);
        }

        // Hanya satu perangkat aktif: perangkat ini mengambil alih
        $kode = Str::random(40);
        $p->update(['sesi_kode' => $kode]);
        $request->session()->put('sesi.'.$ujian->id, $kode);

        $soals = $ujian->soals()->get()->keyBy('id');
        $jaw = $p->jawabans()->get()->keyBy('soal_id');

        // PENTING: kunci jawaban TIDAK dikirim ke browser
        $daftar = collect($p->urutan_soal)->map(function ($id) use ($soals, $jaw, $p, $ujian) {
            $s = $soals[$id];
            $item = [
                'id' => $s->id,
                'tipe' => $s->tipe,
                'teks' => $s->teks,
                'gambar' => $s->gambar ? route('cbt.gambar', [$ujian, $s]) : null,
                'j' => $jaw[$id]->jawaban ?? '',
                'r' => (bool) ($jaw[$id]->ragu ?? false),
                'opsi' => [],
            ];

            if ($s->tipe === 'pg') {
                $huruf = $p->urutan_opsi[$id] ?? array_keys($s->opsi ?? []);
                $item['opsi'] = collect($huruf)
                    ->map(fn ($k) => ['k' => $k, 't' => $s->opsi[$k] ?? ''])
                    ->values()
                    ->all();
            }

            return $item;
        })->values()->all();

        return view('cbt.kerjakan', [
            'ujian' => $ujian,
            'p' => $p,
            'cfg' => [
                'soals' => $daftar,
                'sisa' => $p->sisaDetik(),
                'penyimpanan' => 'cbt-'.$p->id,
                'urlJawab' => route('cbt.jawab', $ujian),
                'urlPelanggaran' => route('cbt.pelanggaran', $ujian),
                'urlHasil' => route('cbt.hasil', $ujian),
                'urlMasuk' => route('cbt.masuk'),
            ],
        ]);
    }

    public function jawab(Request $request, Ujian $ujian)
    {
        $this->bolehLihat($request, $ujian);

        $p = $this->pengerjaan($request, $ujian);
        abort_unless($p, 404);

        if ($p->status !== 'berjalan') {
            return response()->json(['selesai' => true], 410);
        }

        if (! $this->sesiSah($request, $ujian, $p)) {
            return response()->json([
                'pesan' => 'Ujian ini dibuka di perangkat lain. Perangkat ini dihentikan.',
            ], 409);
        }

        if (now()->gt($p->batas_pada->copy()->addSeconds(10))) {
            KoreksiUjian::selesaikan($p);

            return response()->json(['selesai' => true], 410);
        }

        $data = $request->validate([
            'soal_id' => 'required|integer',
            'jawaban' => 'nullable|string|max:5000',
            'ragu' => 'nullable|boolean',
        ]);

        abort_unless(in_array((int) $data['soal_id'], $p->urutan_soal ?? [], true), 422);

        Jawaban::updateOrCreate(
            ['pengerjaan_id' => $p->id, 'soal_id' => (int) $data['soal_id']],
            [
                'jawaban' => $data['jawaban'] ?? null,
                'ragu' => (bool) ($data['ragu'] ?? false),
            ]
        );

        return response()->json(['sisa' => $p->sisaDetik()]);
    }

    public function pelanggaran(Request $request, Ujian $ujian)
    {
        $this->bolehLihat($request, $ujian);

        $p = $this->pengerjaan($request, $ujian);
        if ($p && $p->status === 'berjalan' && $this->sesiSah($request, $ujian, $p)) {
            $p->increment('pelanggaran');
        }

        return response()->noContent();
    }

    public function selesai(Request $request, Ujian $ujian)
    {
        $this->bolehLihat($request, $ujian);

        $p = $this->pengerjaan($request, $ujian);
        abort_unless($p, 404);

        // Hanya perangkat aktif yang boleh mengumpulkan
        if ($p->status === 'berjalan' && $this->sesiSah($request, $ujian, $p)) {
            KoreksiUjian::selesaikan($p);
        }

        return redirect()->route('cbt.hasil', $ujian);
    }

    public function hasil(Request $request, Ujian $ujian)
    {
        $this->bolehLihat($request, $ujian);

        $p = $this->pengerjaan($request, $ujian);
        if (! $p) {
            return redirect()->route('cbt.daftar');
        }

        if ($p->status === 'berjalan') {
            return redirect()->route('cbt.kerjakan', $ujian);
        }

        return view('cbt.hasil', compact('ujian', 'p'));
    }

    // Gambar soal hanya untuk siswa yang sedang/pernah mengerjakan ujian itu
    public function gambar(Request $request, Ujian $ujian, Soal $soal)
    {
        $this->bolehLihat($request, $ujian);

        $p = $this->pengerjaan($request, $ujian);
        abort_unless(
            $p && $soal->gambar && in_array($soal->id, $p->urutan_soal ?? [], true)
            && Storage::disk('local')->exists($soal->gambar),
            404
        );

        return response()->file(Storage::disk('local')->path($soal->gambar), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /* ---------- Bantu ---------- */

    private function akun(Request $request): AkunSiswa
    {
        return $request->attributes->get('akun');
    }

    private function bolehLihat(Request $request, Ujian $ujian): void
    {
        abort_unless($ujian->kelas_id === $this->akun($request)->siswa->kelas_id, 404);
    }

    private function pengerjaan(Request $request, Ujian $ujian): ?Pengerjaan
    {
        return Pengerjaan::where('ujian_id', $ujian->id)
            ->where('siswa_id', $this->akun($request)->siswa_id)
            ->first();
    }

    private function sesiSah(Request $request, Ujian $ujian, Pengerjaan $p): bool
    {
        return $p->sesi_kode
            && hash_equals((string) $p->sesi_kode, (string) $request->session()->get('sesi.'.$ujian->id));
    }
}