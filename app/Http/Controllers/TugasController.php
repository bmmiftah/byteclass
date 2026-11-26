<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\NilaiDetail;
use App\Models\NilaiSiswa;
use App\Models\Penilaian;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\TugasBerkas;
use App\Models\TugasPengumpulan;
use App\Services\BersihkanHtml;
use App\Services\PenyimpananTugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use	App\Models\Rubrik;
use	App\Models\TugasNilaiKriteria;
use	App\Services\RubrikTugas;

/**
 * Sisi guru untuk Tugas. Semua route-nya ada di grup ['auth','aktif','milik'],
 * jadi kepemilikan {kelas}, {tugas}, {pengumpulan}, {berkas}, {siswa} sudah dijaga middleware.
 */
class TugasController extends Controller
{
    /* ---------- daftar ---------- */

    public function index(Kelas $kelas)
    {
        $tugas = Tugas::where('kelas_id', $kelas->id)
            ->withCount('pengumpulans')
            ->latest('tenggat')
            ->get();

        $jumlahSiswa = $kelas->siswas()->where('aktif', true)->count();

        $pakaiKelas = (int) DB::table('tugas_berkas')
            ->join('tugas_pengumpulans', 'tugas_pengumpulans.id', '=', 'tugas_berkas.pengumpulan_id')
            ->join('tugas', 'tugas.id', '=', 'tugas_pengumpulans.tugas_id')
            ->where('tugas.kelas_id', $kelas->id)
            ->whereNotNull('tugas_berkas.path')
            ->sum('tugas_berkas.ukuran');

        $pakaiGuru = Tugas::pemakaianGuru(auth()->id());
        $penuh = PenyimpananTugas::hampirPenuh();

        return view('tugas.index', compact('kelas', 'tugas', 'jumlahSiswa', 'pakaiKelas', 'pakaiGuru', 'penuh'));
    }

    /* ---------- buat & ubah ---------- */

    public function create(Kelas $kelas)
    {
        return view('tugas.form', [
            'kelas'  => $kelas,
            'tugas'  => new Tugas([
                'jenis'        => 'file',
                'telat'        => 'tutup',
                'boleh_ulang'  => true,
                'maks_berkas'  => 3,
                'ukuran_mb'    => 10,
                'jenis_berkas' => 'umum',
            ]),
            'mapels' => auth()->user()->mapels()->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request, Kelas $kelas)
    {
        $data = $this->validasi($request);

        $tugas = new Tugas($data);
        $tugas->kelas_id = $kelas->id;
        // Jadwal tayang diisi = otomatis terbit, siswa melihatnya begitu waktunya tiba.
        $tugas->status = $tugas->terbit_pada ? 'terbit' : 'draf';
        $tugas->save();

        return redirect()
            ->route('tugas.hasil', $tugas)
            ->with('ok', $this->pesanSimpan($tugas, true));
    }

    public function edit(Tugas $tugas)
    {
        return view('tugas.form', [
            'kelas'  => $tugas->kelas,
            'tugas'  => $tugas,
            'mapels' => auth()->user()->mapels()->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Tugas $tugas)
    {
        abort_if($tugas->file_dibersihkan_pada, 422, 'File tugas ini sudah dibersihkan.');

        $data = $this->validasi($request);
        $tugas->fill($data);

        // Draf yang diberi jadwal tayang ikut otomatis terbit.
        if ($tugas->status === 'draf' && $tugas->terbit_pada) {
            $tugas->status = 'terbit';
        }

        $tugas->save();

        return redirect()
            ->route('tugas.hasil', $tugas)
            ->with('ok', $this->pesanSimpan($tugas, false));
    }

    private function pesanSimpan(Tugas $tugas, bool $baru): string
    {
        if ($tugas->status === 'terbit' && $tugas->terbit_pada) {
            $jam = $tugas->terbit_pada->locale('id')->translatedFormat('j F Y H.i');

            return $tugas->terbit_pada->isFuture()
                ? 'Tugas ' . ($baru ? 'dibuat' : 'disimpan') . ' dan terjadwal. Otomatis muncul di siswa pada ' . $jam . '.'
                : 'Tugas ' . ($baru ? 'dibuat' : 'disimpan') . ' dan sudah terbit. Siswa sudah bisa melihatnya.';
        }

        return $baru
            ? 'Tugas dibuat sebagai draf. Terbitkan kalau sudah siap dilihat siswa.'
            : 'Tugas disimpan.';
    }

    private function validasi(Request $request): array
    {
        $d = $request->validate([
            'judul'            => 'required|string|max:150',
            'instruksi'        => 'nullable|string|max:60000',
            'jenis'            => 'required|in:file,link,teks,eksternal',
            'tenggat'          => 'required|date',
            'telat'            => 'required|in:tutup,terima',
            'boleh_ulang'      => 'nullable|boolean',
            'maks_berkas'      => 'required|integer|min:1|max:10',
            'ukuran_mb'        => 'required|integer|min:1|max:50',
            'jenis_berkas'     => 'required|in:umum,gambar,dokumen',
            'tautan_eksternal' => 'nullable|required_if:jenis,eksternal|url:http,https|max:500',
            'kode_konfirmasi'  => 'nullable|string|max:20',
            'mapel_id'         => 'nullable|integer',
            'terbit_pada'      => 'nullable|date',
        ], [
            'tautan_eksternal.required_if' => 'Isi link folder untuk jenis "Folder / link di luar".',
        ]);

        $d['instruksi'] = BersihkanHtml::proses($d['instruksi'] ?? null);
        $d['boleh_ulang'] = $request->boolean('boleh_ulang');
        $d['mapel_id'] = ($d['mapel_id'] ?? null)
            ? auth()->user()->mapels()->whereKey($d['mapel_id'])->value('id')
            : null;
        $d['kode_konfirmasi'] = ($d['kode_konfirmasi'] ?? '') !== ''
            ? trim($d['kode_konfirmasi'])
            : null;

        if ($d['jenis'] !== 'eksternal') {
            $d['tautan_eksternal'] = null;
            $d['kode_konfirmasi'] = null;
        }

        return $d;
    }

    /* ---------- hasil per tugas ---------- */

    public function hasil(Tugas $tugas)
    {
        $tugas->load('kelas', 'mapel');

        $siswas = $tugas->kelas->siswas()->where('aktif', true)->orderBy('nama')->get();
        $kumpul = $tugas->pengumpulans()->with('berkas')->get()->keyBy('siswa_id');

        $ringkas = [
            'siswa'   => $siswas->count(),
            'kumpul'  => $kumpul->whereIn('siswa_id', $siswas->pluck('id'))->count(),
            'dinilai' => $kumpul->whereNotNull('nilai')->count(),
            'telat'   => $kumpul->where('telat', true)->count(),
        ];

        $pakaiTugas = $tugas->ukuranBerkasAda();
        $jumlahFile = $tugas->jumlahBerkasAda();
        $sisaHari = $tugas->hapus_pada ? (int) ceil(now()->diffInHours($tugas->hapus_pada, false) / 24) : null;

        // Fase 9D: rubrik
        $kriterias = $tugas->kriterias()->get();
        $rubriks   = Rubrik::where('guru_id', auth()->id())->withCount('kriterias')->orderBy('nama')->get();
        $skor      = TugasNilaiKriteria::whereIn('pengumpulan_id', $kumpul->pluck('id'))->get()
            ->groupBy('pengumpulan_id')->map(fn ($g) => $g->pluck('nilai', 'tugas_kriteria_id'));

        return view('tugas.hasil', compact('tugas', 'siswas', 'kumpul', 'ringkas', 'pakaiTugas', 'jumlahFile', 'sisaHari', 'kriterias', 'rubriks', 'skor'));
    }

    /* ---------- status: terbit, arsip ---------- */

    public function terbit(Tugas $tugas)
    {
        abort_unless($tugas->status === 'draf', 422);

        $tugas->update(['status' => 'terbit']);

        return back()->with(
            'ok',
            $tugas->terbit_pada && $tugas->terbit_pada->isFuture()
                ? 'Tugas diterbitkan, akan muncul di siswa pada jadwal tayangnya.'
                : 'Tugas diterbitkan. Siswa sudah bisa melihatnya.'
        );
    }

    public function tarik(Tugas $tugas)
    {
        abort_unless($tugas->status === 'terbit', 422);

        if ($tugas->pengumpulans()->exists()) {
            return back()->withErrors(['aksi' => 'Sudah ada siswa yang mengumpulkan, jadi tugas tidak bisa ditarik. Arsipkan saja.']);
        }

        $tugas->update(['status' => 'draf']);

        return back()->with('ok', 'Tugas ditarik jadi draf.');
    }

    public function arsip(Tugas $tugas)
    {
        abort_unless(in_array($tugas->status, ['terbit', 'draf'], true), 422);

        $hari = auth()->user()->hapus_otomatis_hari;
        $tugas->update([
            'status'          => 'arsip',
            'diarsipkan_pada' => now(),
            'hapus_pada'      => $hari ? now()->addDays($hari) : null,
        ]);

        return back()->with('ok', 'Tugas diarsipkan. Siswa tidak bisa mengumpulkan lagi. File masih aman.');
    }

    public function bukaArsip(Tugas $tugas)
    {
        abort_unless($tugas->status === 'arsip', 422);

        if ($tugas->file_dibersihkan_pada) {
            return back()->withErrors(['aksi' => 'File tugas ini sudah dibersihkan, jadi tidak bisa dibuka lagi.']);
        }

        $tugas->update([
            'status'          => 'terbit',
            'diarsipkan_pada' => null,
            'hapus_pada'      => null,
        ]);

        return back()->with('ok', 'Tugas dibuka lagi.');
    }

    /** Geser jadwal hapus otomatis, atau batalkan untuk tugas ini */
    public function tunda(Request $request, Tugas $tugas)
    {
        abort_unless($tugas->status === 'arsip' && $tugas->hapus_pada && ! $tugas->file_dibersihkan_pada, 422);

        if ($request->input('aksi') === 'batal') {
            $tugas->update(['hapus_pada' => null]);

            return back()->with('ok', 'Hapus otomatis untuk tugas ini dibatalkan.');
        }

        $dasar = $tugas->hapus_pada->isFuture() ? $tugas->hapus_pada : now();
        $tugas->update(['hapus_pada' => $dasar->copy()->addDays((int) config('tugas.hari_tunda', 14))]);

        return back()->with('ok', 'Jadwal hapus digeser ' . config('tugas.hari_tunda', 14) . ' hari.');
    }

    /* ---------- unduh ---------- */

    public function zip(Tugas $tugas)
    {
        $tugas->load('kelas');

        $tmp = PenyimpananTugas::zip($tugas);
        if (! $tmp) {
            return back()->withErrors(['aksi' => 'Tidak ada file untuk diunduh.']);
        }

        $nama = Str::slug($tugas->kelas->nama . ' ' . $tugas->judul, '-') . '.zip';

        return response()->download($tmp, $nama, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function berkas(TugasBerkas $berkas)
    {
        return PenyimpananTugas::kirimBerkas($berkas);
    }

    /* ---------- pembersihan (pakai password) ---------- */

    public function bersihkan(Request $request, Tugas $tugas)
    {
        abort_unless($tugas->status === 'arsip', 422, 'Arsipkan tugas dulu sebelum membersihkan file.');

        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'Password salah. File tidak dihapus.']);
        }

        [$jumlah, $byte] = PenyimpananTugas::bersihkan($tugas);

        return back()->with(
            'ok',
            "File dibersihkan: $jumlah file (" . Number::fileSize($byte, 1) . '). Nilai, komentar, dan catatan waktu tetap tersimpan.'
        );
    }

    public function destroy(Request $request, Tugas $tugas)
    {
        $request->validate(['password' => 'required|string']);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'Password salah. Tugas tidak dihapus.']);
        }

        $kelasId = $tugas->kelas_id;
        PenyimpananTugas::hapusSemua($tugas);
        $tugas->delete();

        return redirect()
            ->route('tugas.index', $kelasId)
            ->with('ok', 'Tugas dihapus beserta semua pengumpulannya.');
    }

    /** Pengaturan hapus otomatis milik guru (mengaktifkan/mengubah wajib password) */
    public function pengaturan(Request $request)
    {
        $d = $request->validate([
            'hari'     => 'nullable|integer|min:7|max:365',
            'password' => 'nullable|string',
        ]);

        $user = $request->user();
        $hari = ($d['hari'] ?? null) ?: null;

        if ($hari !== null) {
            if (! Hash::check((string) ($d['password'] ?? ''), $user->password)) {
                return back()->withErrors(['password' => 'Password salah. Pengaturan tidak diubah.']);
            }
        }

        $user->forceFill(['hapus_otomatis_hari' => $hari])->save();

        // Tugas arsip yang filenya belum dibersihkan: hitung ulang mulai sekarang, tidak pernah mundur ke masa lalu
        Tugas::whereHas('kelas', fn ($k) => $k->where('guru_id', $user->id))
            ->where('status', 'arsip')
            ->whereNull('file_dibersihkan_pada')
            ->update(['hapus_pada' => $hari ? now()->addDays($hari) : null]);

        return back()->with(
            'ok',
            $hari
                ? "Hapus otomatis aktif: file tugas dibersihkan $hari hari setelah diarsipkan."
                : 'Hapus otomatis dimatikan.'
        );
    }

    /* ---------- penilaian per siswa ---------- */

    public function nilai(Request $request, TugasPengumpulan $pengumpulan)
    {

       // Fase 9D: tugas yang memakai rubrik dinilai per kriteria
        $pengumpulan->loadMissing('tugas');
        if ($pengumpulan->tugas->kriterias()->exists()) {
            $d = $request->validate([
                'komentar'   => 'nullable|string|max:1000',
                'kriteria'   => 'nullable|array',
                'kriteria.*' => 'nullable',
            ]);

            $hasil = RubrikTugas::simpanNilai($pengumpulan, (array) ($d['kriteria'] ?? []));
            if ($hasil['galat']) {
                return back()->withErrors(['nilai' => $hasil['galat']])->withInput();
            }

            $pengumpulan->update([
                'komentar'           => ($d['komentar'] ?? '') !== '' ? $d['komentar'] : null,
                'terverifikasi_pada' => $pengumpulan->terverifikasi_pada ?? now(),
            ]);

            return back()->with('ok', 'Tersimpan.');
        } 

        $d = $request->validate([
            'nilai'    => 'nullable|numeric|min:0|max:100',
            'komentar' => 'nullable|string|max:1000',
        ]);

        $nilai = ($d['nilai'] ?? null) === null || $d['nilai'] === ''
            ? null
            : round((float) $d['nilai'], 2);

        $pengumpulan->update([
            'nilai'              => $nilai,
            'komentar'           => ($d['komentar'] ?? '') !== '' ? $d['komentar'] : null,
            'terverifikasi_pada' => $pengumpulan->terverifikasi_pada ?? now(),
            'dinilai_pada'       => $nilai === null ? null : now(),
        ]);

        return back()->with('ok', 'Tersimpan.');
    }

    /** Guru menandai siswa "sudah kumpul" (mis. kertas diserahkan langsung) */
    public function tandai(Tugas $tugas, Siswa $siswa)
    {
        abort_unless($siswa->kelas_id === $tugas->kelas_id, 404);

        TugasPengumpulan::firstOrCreate(
            ['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id],
            [
                'dikirim_pada'       => now(),
                'telat'              => false,
                'ditandai_guru'      => true,
                'terverifikasi_pada' => now(),
            ]
        );

        return back()->with('ok', $siswa->nama . ' ditandai sudah mengumpulkan.');
    }

    /** Kirim nilai tugas ke modul Penilaian (seperti CBT) */
    public function kirimNilai(Tugas $tugas)
    {
        if (! $tugas->pengumpulans()->whereNotNull('nilai')->exists()) {
            return back()->withErrors(['aksi' => 'Belum ada nilai yang diisi.']);
        }

        $penilaian = DB::transaction(function () use ($tugas) {
            $penilaian = $tugas->penilaian ?? Penilaian::create([
                'kelas_id' => $tugas->kelas_id,
                'mapel_id' => $tugas->mapel_id,
                'judul'    => 'Tugas: ' . $tugas->judul,
                'jenis'    => 'individu',
                'tanggal'  => today(),
                'kkm'      => 75,
            ]);

            // Fase 9D: tugas berrubrik mengisi satu kolom per kriteria di Penilaian
            $peta = RubrikTugas::sinkronKriteria($penilaian, $tugas);
            $kriteria = $peta !== null ? null : ($penilaian->kriterias()->first() ?? $penilaian->kriterias()->create([
                'nama'   => 'Nilai Tugas',
                'tipe'   => 'angka',
                'maks'   => 100,
                'bobot'  => 1,
                'urutan' => 0,
            ]));

            foreach ($tugas->pengumpulans()->whereNotNull('nilai')->get() as $p) {
                $ns = NilaiSiswa::updateOrCreate(
                    ['penilaian_id' => $penilaian->id, 'siswa_id' => $p->siswa_id],
                    ['total' => $p->nilai]
                );

                if ($peta === null) {
                    NilaiDetail::updateOrCreate(
                        ['nilai_siswa_id' => $ns->id, 'kriteria_id' => $kriteria->id],
                        ['nilai' => $p->nilai]
                    );
                    continue;
                }

                $skor = TugasNilaiKriteria::where('pengumpulan_id', $p->id)->pluck('nilai', 'tugas_kriteria_id');

                foreach ($peta as $tugasKriteriaId => $kriteriaPenilaian) {
                    if ($skor->has($tugasKriteriaId)) {
                        NilaiDetail::updateOrCreate(
                            ['nilai_siswa_id' => $ns->id, 'kriteria_id' => $kriteriaPenilaian->id],
                            ['nilai' => $skor->get($tugasKriteriaId)]
                        );
                    } else {
                        NilaiDetail::where('nilai_siswa_id', $ns->id)->where('kriteria_id', $kriteriaPenilaian->id)->delete();
                    }
                }
            }

            $tugas->update(['penilaian_id' => $penilaian->id]);

            return $penilaian;
        });

        return redirect()
            ->route('penilaian.show', $penilaian)
            ->with('ok', 'Nilai tugas masuk ke Penilaian.');
    }
}