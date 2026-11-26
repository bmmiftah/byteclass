<?php

namespace App\Http\Controllers;

use App\Models\GamifikasiPengaturan;
use App\Models\Kelas;
use App\Models\PoinSiswa;
use App\Models\Siswa;
use App\Services\Gamifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Poin & lencana (sisi guru). Semua route-nya berada di grup ['auth','aktif','milik']. */
class PoinController extends Controller
{
    public function index(Kelas $kelas)
    {
        $p = GamifikasiPengaturan::untuk($kelas->id);
        $papan = $tim = $riwayat = [];
        $target = null;

        if ($p->aktif) {
            Gamifikasi::sinkron($kelas, true);
            $papan = Gamifikasi::papan($kelas, $p);
            $tim = Gamifikasi::tim($kelas, $p);
            $target = Gamifikasi::target($kelas, $p);
            $riwayat = PoinSiswa::with('siswa')
                ->where('kelas_id', $kelas->id)
                ->where('terjadi_pada', '>=', $p->mulai_pada->toDateString())
                ->orderByDesc('terjadi_pada')
                ->orderByDesc('id')
                ->limit(40)
                ->get();
        }

        $sesis = DB::table('kelompok_sesis')
            ->where('kelas_id', $kelas->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get(['id', 'nama']);

        return view('poin.index', compact('kelas', 'p', 'papan', 'tim', 'target', 'riwayat', 'sesis'));
    }

    /** Hidupkan atau matikan gamifikasi kelas ini. Poin yang sudah tercatat tidak dihapus saat dimatikan. */
    public function aktif(Request $request, Kelas $kelas)
    {
        $data = $request->validate([
            'aktif'      => 'required|boolean',
            'mulai_pada' => 'nullable|date|before_or_equal:' . today()->toDateString(),
        ]);

        $p = GamifikasiPengaturan::untuk($kelas->id);
        $p->aktif = (bool) $data['aktif'];

        // tanggal mulai hanya ditetapkan saat pertama kali dihidupkan; setelah itu diubah lewat Pengaturan
        if (! $p->exists) {
            $p->mulai_pada = $data['mulai_pada'] ?? today();
        }

        $p->save();

        return redirect()->route('poin.index', $kelas)
            ->with('ok', $p->aktif ? 'Gamifikasi dihidupkan untuk kelas ini.' : 'Gamifikasi dimatikan. Poin yang sudah ada tetap tersimpan.');
    }

    public function pengaturan(Request $request, Kelas $kelas)
    {
        $p = GamifikasiPengaturan::untuk($kelas->id);
        if (! $p->exists) {
            return back()->withErrors(['umum' => 'Hidupkan gamifikasi dulu.']);
        }

        $hariIni = today()->toDateString();
        $data = $request->validate([
            'mulai_pada'       => 'required|date|before_or_equal:' . $hariIni,
            'papan_individu'   => 'nullable|boolean',
            'kelompok_sesi_id' => 'nullable|integer',
            'tim_mulai'        => 'nullable|date|before_or_equal:' . $hariIni,
            'target_poin'      => 'nullable|integer|min:10|max:100000',
            'target_judul'     => 'nullable|string|max:80',
            'target_mulai'     => 'nullable|date|before_or_equal:' . $hariIni,
        ], [
            'mulai_pada.required'             => 'Tanggal mulai wajib diisi.',
            'mulai_pada.before_or_equal'    => 'Tanggal mulai tidak boleh di masa depan.',
            'tim_mulai.before_or_equal'     => 'Awal putaran tim tidak boleh di masa depan.',
            'target_mulai.before_or_equal'  => 'Awal target tidak boleh di masa depan.',
            'target_poin.min'                 => 'Target minimal 10 poin.',
        ]);

        // sesi kelompok harus milik kelas ini (dan belum dihapus)
        $sesiId = $data['kelompok_sesi_id'] ?? null;
        if ($sesiId && ! DB::table('kelompok_sesis')->where('id', $sesiId)->where('kelas_id', $kelas->id)->whereNull('deleted_at')->exists()) {
            return back()->withInput()->withErrors(['kelompok_sesi_id' => 'Pembagian kelompok itu bukan milik kelas ini.']);
        }

        $baru = $sesiId && (int) $sesiId !== (int) $p->kelompok_sesi_id;
        $p->fill([
            'mulai_pada'       => $data['mulai_pada'],
            'papan_individu'   => $request->boolean('papan_individu'),
            'kelompok_sesi_id' => $sesiId ?: null,
            'tim_mulai'        => $data['tim_mulai'] ?? null,
            'target_poin'      => $data['target_poin'] ?? null,
            'target_judul'     => ($data['target_judul'] ?? null) ?: null,
            'target_mulai'     => $data['target_mulai'] ?? null,
        ]);

        // memilih pembagian kelompok yang baru = papan tim mulai dari nol hari ini, kecuali guru mengisi tanggalnya sendiri
        if ($baru && empty($data['tim_mulai'])) {
            $p->tim_mulai = today();
        }

        // target baru (atau target yang tadinya kosong) mulai dihitung hari ini kalau guru tidak mengisi tanggalnya
        if ($p->target_poin && empty($data['target_mulai']) && ! $p->getOriginal('target_poin')) {
            $p->target_mulai = today();
        }

        $p->save();

        return redirect()->route('poin.index', $kelas)->with('ok', 'Pengaturan disimpan.');
    }

    /** Mulai putaran tim baru, atau target baru, dari hari ini. Poin siswa tidak berubah. */
    public function mulaiBaru(Request $request, Kelas $kelas)
    {
        $data = $request->validate(['untuk' => 'required|in:tim,target']);
        $p = GamifikasiPengaturan::untuk($kelas->id);
        if (! $p->exists) {
            return back()->withErrors(['umum' => 'Hidupkan gamifikasi dulu.']);
        }

        $kolom = $data['untuk'] === 'tim' ? 'tim_mulai' : 'target_mulai';
        $p->forceFill([$kolom => today()])->save();

        return redirect()->route('poin.index', $kelas)
            ->with('ok', $data['untuk'] === 'tim' ? 'Putaran tim baru dimulai hari ini.' : 'Hitungan target dimulai ulang dari hari ini.');
    }

    /** Beri (atau kurangi) poin dari guru untuk satu atau banyak siswa sekaligus. */
    public function beri(Request $request, Kelas $kelas)
    {
        $p = GamifikasiPengaturan::untuk($kelas->id);
        if (! $p->aktif) {
            return back()->withErrors(['umum' => 'Hidupkan gamifikasi dulu sebelum memberi poin.']);
        }

        $maks = Gamifikasi::MAKS_MANUAL;
        $data = $request->validate([
            'siswa_ids'   => 'required|array|min:1|max:80',
            'siswa_ids.*' => 'integer',
            'poin'        => "required|integer|between:-{$maks},{$maks}|not_in:0",
            'keterangan'  => 'required|string|min:3|max:150',
        ], [
            'siswa_ids.required'  => 'Pilih minimal satu siswa.',
            'siswa_ids.min'       => 'Pilih minimal satu siswa.',
            'poin.between'        => "Poin harus antara -{$maks} dan {$maks}.",
            'poin.not_in'         => 'Poin tidak boleh 0.',
            'keterangan.required' => 'Tulis alasannya (siswa akan membacanya).',
            'keterangan.min'      => 'Alasannya terlalu pendek.',
        ]);

        $minta = collect($data['siswa_ids'])->map(fn ($v) => (int) $v)->unique()->values();

        // semua siswa yang dipilih harus aktif DAN milik kelas ini; kalau ada satu yang bukan, tidak ada poin yang disimpan
        $sah = Siswa::where('kelas_id', $kelas->id)->where('aktif', true)->whereIn('id', $minta)->pluck('id');
        if ($sah->count() !== $minta->count()) {
            return back()->withInput()->withErrors(['siswa_ids' => 'Ada siswa yang bukan anggota kelas ini.']);
        }

        // penjaga tekan dua kali: pemberian yang sama persis dalam 20 detik terakhir diabaikan
        $kembar = PoinSiswa::where('kelas_id', $kelas->id)
            ->where('jenis', 'manual')
            ->where('guru_id', $request->user()->id)
            ->where('poin', (int) $data['poin'])
            ->where('keterangan', $data['keterangan'])
            ->where('created_at', '>=', now()->subSeconds(20))
            ->whereIn('siswa_id', $sah)
            ->exists();

        if ($kembar) {
            return redirect()->route('poin.index', $kelas)->with('ok', 'Poin yang sama baru saja diberikan, jadi tidak dicatat dua kali.');
        }

        $kini = now();
        DB::table('poin_siswas')->insert($sah->map(fn ($id) => [
            'kelas_id'     => $kelas->id,
            'siswa_id'     => $id,
            'jenis'        => 'manual',
            'sumber_tipe'  => null,
            'sumber_id'    => null,
            'poin'         => (int) $data['poin'],
            'keterangan'   => $data['keterangan'],
            'guru_id'      => $request->user()->id,
            'terjadi_pada' => today()->toDateString(),
            'created_at'   => $kini,
            'updated_at'   => $kini,
        ])->all());

        // lencana yang baru terpenuhi (mis. Seratus Poin) langsung dibagikan
        Gamifikasi::sinkron($kelas, true);

        return redirect()->route('poin.index', $kelas)
            ->with('ok', ($data['poin'] > 0 ? '+' : '') . $data['poin'] . ' poin untuk ' . $sah->count() . ' siswa disimpan.');
    }

    /** Batalkan pemberian poin manual. Poin otomatis tidak bisa dihapus (akan muncul lagi); kurangi dengan poin negatif. */
    public function hapus(Request $request, PoinSiswa $poin)
    {
        if (! $poin->manual()) {
            return back()->withErrors(['umum' => 'Poin otomatis tidak bisa dihapus. Untuk mengoreksi, beri poin negatif beserta alasannya.']);
        }

        $kelasId = $poin->kelas_id;
        $poin->delete();

        return redirect()->route('poin.index', $kelasId)->with('ok', 'Pemberian poin dibatalkan.');
    }
}