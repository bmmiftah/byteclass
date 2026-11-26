<?php

namespace App\Services;

use App\Models\GamifikasiPengaturan;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Gamifikasi kelas: poin USAHA (bukan poin nilai), lencana otomatis, papan tim, dan target kelas.
 *
 * Cara kerjanya: poin ditulis ke buku besar `poin_siswas`. Poin otomatis DIHITUNG ULANG dari data yang sudah ada
 * (pengumpulan tugas, ujian selesai, presensi) oleh sinkron(), dan tiap kejadian sumber hanya bisa memberi poin
 * sekali (kunci unik), jadi sinkron() boleh dipanggil berkali-kali tanpa menggandakan poin.
 */
class Gamifikasi
{
    /** Poin per kejadian. Ubah angkanya di sini saja; poin lama yang sudah tercatat tidak berubah. */
    public const POIN = [
        'tugas_tepat' => 10,
        'tugas_telat' => 5,
        'ujian'       => 10,
        'hadir'       => 2,
        'pekan'       => 10,
    ];

    public const JENIS = [
        'tugas_tepat' => 'Tugas tepat waktu',
        'tugas_telat' => 'Tugas (terlambat)',
        'ujian'       => 'Mengerjakan ujian',
        'hadir'       => 'Hadir di kelas',
        'pekan'       => 'Hadir penuh sepekan',
        'manual'      => 'Dari guru',
    ];

    /** Sepekan penuh hanya dihitung bila pekan itu punya presensi minimal sekian hari. */
    public const PEKAN_MIN_HARI = 3;

    /** Sinkron otomatis dari halaman siswa paling sering sekali per kelas dalam sekian detik. */
    public const JEDA_SINKRON = 45;

    public const MAKS_MANUAL = 50;

    /** kode => [nama, syarat (teks untuk siswa), ikon] */
    public const LENCANA = [
        'langkah_pertama' => ['Langkah Pertama', 'Mendapat poin pertama', ' '],
        'rajin_5'         => ['Rajin', 'Mengumpulkan 5 tugas tepat waktu', '✏ '],
        'rajin_15'        => ['Super Rajin', 'Mengumpulkan 15 tugas tepat waktu', ''],
        'pejuang_ujian'   => ['Pejuang Ujian', 'Menyelesaikan 3 ujian', ' '],
        'hadir_pekan'     => ['Hadir Sepekan', 'Hadir penuh dalam satu pekan', ' '],
        'konsisten'       => ['Konsisten', 'Hadir penuh dalam 4 pekan', ' '],
        'seratus'         => ['Seratus Poin', 'Mengumpulkan 100 poin', '⭐'],
        'tiga_ratus'      => ['Tiga Ratus Poin', 'Mengumpulkan 300 poin', ' '],
    ];

    public static function kunciSinkron(int $kelasId): string
    {
        return 'gamifikasi:sinkron:' . $kelasId;
    }

    // ------------------------------------------------------------------ sinkron

    /** Salin kejadian nyata ke buku besar, lalu bagikan lencana. Tidak melakukan apa pun bila gamifikasi mati. */
    public static function sinkron(Kelas $kelas, bool $paksa = false): void
    {
        $p = GamifikasiPengaturan::where('kelas_id', $kelas->id)->first();
        if (! $p || ! $p->aktif) {
            return;
        }

        if (! $paksa && ! Cache::add(self::kunciSinkron($kelas->id), 1, self::JEDA_SINKRON)) {
            return;
        }

        $mulai = $p->mulai_pada->toDateString();
        $ids = Siswa::where('kelas_id', $kelas->id)->where('aktif', true)->pluck('id')->all();
        if (! $ids) {
            return;
        }

        $sekarang = now();
        $baris = [];
        $tambah = function (int $siswa, string $jenis, string $tipe, int $sumber, string $tanggal) use (&$baris, $kelas, $sekarang) {
            $baris[] = [
                'kelas_id'     => $kelas->id,
                'siswa_id'     => $siswa,
                'jenis'        => $jenis,
                'sumber_tipe'  => $tipe,
                'sumber_id'    => $sumber,
                'poin'         => self::POIN[$jenis],
                'keterangan'   => null,
                'guru_id'      => null,
                'terjadi_pada' => $tanggal,
                'created_at'   => $sekarang,
                'updated_at'   => $sekarang,
            ];
        };

        // 1) tugas yang dikumpulkan
        DB::table('tugas_pengumpulans as pg')
            ->join('tugas as t', 't.id', '=', 'pg.tugas_id')
            ->where('t.kelas_id', $kelas->id)
            ->whereIn('pg.siswa_id', $ids)
            ->whereDate('pg.dikirim_pada', '>=', $mulai)
            ->get(['pg.id', 'pg.siswa_id', 'pg.telat', 'pg.dikirim_pada'])
            ->each(fn ($r) => $tambah((int) $r->siswa_id, $r->telat ? 'tugas_telat' : 'tugas_tepat', 'tugas', (int) $r->id, substr($r->dikirim_pada, 0, 10)));

        // 2) ujian yang diselesaikan
        DB::table('pengerjaans as pk')
            ->join('ujians as u', 'u.id', '=', 'pk.ujian_id')
            ->where('u.kelas_id', $kelas->id)
            ->where('pk.status', 'selesai')
            ->whereNotNull('pk.selesai_pada')
            ->whereIn('pk.siswa_id', $ids)
            ->whereDate('pk.selesai_pada', '>=', $mulai)
            ->get(['pk.id', 'pk.siswa_id', 'pk.selesai_pada'])
            ->each(fn ($r) => $tambah((int) $r->siswa_id, 'ujian', 'ujian', (int) $r->id, substr($r->selesai_pada, 0, 10)));

        // 3) kehadiran, dan 4) pekan penuh
        $presensi = DB::table('presensis')
            ->where('kelas_id', $kelas->id)
            ->whereIn('siswa_id', $ids)
            ->whereDate('tanggal', '>=', $mulai)
            ->get(['id', 'siswa_id', 'tanggal', 'status']);

        $pekan = [];
        foreach ($presensi as $r) {
            $tgl = substr($r->tanggal, 0, 10);
            $senin = Carbon::parse($tgl)->startOfWeek(Carbon::MONDAY)->toDateString();
            $pekan[$senin]['hari'][$tgl] = true;
            if ($r->status === 'H') {
                $tambah((int) $r->siswa_id, 'hadir', 'presensi', (int) $r->id, $tgl);
                $pekan[$senin]['hadir'][(int) $r->siswa_id][$tgl] = true;
            }
        }

        foreach ($pekan as $senin => $d) {
            $akhir = Carbon::parse($senin)->addDays(6);
            // pekan yang belum selesai, atau yang sebagian jatuh sebelum awal gamifikasi, tidak dihitung
            if ($akhir->gte(today()) || $senin < $mulai) {
                continue;
            }

            $hari = count($d['hari']);
            if ($hari < self::PEKAN_MIN_HARI) {
                continue;
            }

            foreach ($d['hadir'] ?? [] as $siswaId => $tanggalHadir) {
                if (count($tanggalHadir) === $hari) {
                    $tambah($siswaId, 'pekan', 'pekan', (int) Carbon::parse($senin)->format('oW'), $akhir->toDateString());
                }
            }
        }

        foreach (array_chunk($baris, 200) as $bagian) {
            DB::table('poin_siswas')->insertOrIgnore($bagian);
        }

        self::bagiLencana($kelas, $p, $ids);
    }

    private static function bagiLencana(Kelas $kelas, GamifikasiPengaturan $p, array $ids): void
    {
        $stat = self::statistik($kelas, $p);
        $baris = [];
        $kini = now();

        foreach ($ids as $id) {
            $s = $stat[$id] ?? null;
            if (! $s) {
                continue;
            }

            foreach (array_keys(self::LENCANA) as $kode) {
                if (self::memenuhi($kode, $s)) {
                    $baris[] = [
                        'siswa_id'    => $id,
                        'kode'        => $kode,
                        'diraih_pada' => $kini,
                    ];
                }
            }
        }

        foreach (array_chunk($baris, 200) as $bagian) {
            DB::table('lencana_siswas')->insertOrIgnore($bagian);
        }
    }

    /** @return array<int,array{total:int,tugas_tepat:int,ujian:int,pekan:int,ada:bool}> per siswa, sejak awal gamifikasi */
    private static function statistik(Kelas $kelas, GamifikasiPengaturan $p): array
    {
        $hasil = [];
        DB::table('poin_siswas')
            ->where('kelas_id', $kelas->id)
            ->where('terjadi_pada', '>=', $p->mulai_pada->toDateString())
            ->groupBy('siswa_id', 'jenis')
            ->selectRaw('siswa_id, jenis, count(*) as n, sum(poin) as jumlah')
            ->get()
            ->each(function ($r) use (&$hasil) {
                $s = &$hasil[(int) $r->siswa_id];
                $s ??= ['total' => 0, 'tugas_tepat' => 0, 'ujian' => 0, 'pekan' => 0, 'ada' => false];
                $s['total'] += (int) $r->jumlah;
                $s['ada'] = $s['ada'] || (int) $r->jumlah > 0;
                if (isset($s[$r->jenis])) {
                    $s[$r->jenis] += (int) $r->n;
                }
            });

        foreach ($hasil as &$s) {
            $s['total'] = max(0, $s['total']);
        }

        return $hasil;
    }

    private static function memenuhi(string $kode, array $s): bool
    {
        return match ($kode) {
            'langkah_pertama' => $s['ada'],
            'rajin_5'         => $s['tugas_tepat'] >= 5,
            'rajin_15'        => $s['tugas_tepat'] >= 15,
            'pejuang_ujian'   => $s['ujian'] >= 3,
            'hadir_pekan'     => $s['pekan'] >= 1,
            'konsisten'       => $s['pekan'] >= 4,
            'seratus'         => $s['total'] >= 100,
            'tiga_ratus'      => $s['total'] >= 300,
            default           => false,
        };
    }

    // ------------------------------------------------------------------ total, papan, tim, target

    /** @return array<int,int> siswa_id => total poin sejak tanggal itu (tidak pernah di bawah 0), hanya siswa aktif kelas ini */
    public static function total(Kelas $kelas, string $sejak): array
    {
        $ids = Siswa::where('kelas_id', $kelas->id)->where('aktif', true)->pluck('id')->all();
        $out = array_fill_keys($ids, 0);

        DB::table('poin_siswas')
            ->where('kelas_id', $kelas->id)
            ->where('terjadi_pada', '>=', $sejak)
            ->whereIn('siswa_id', $ids)
            ->groupBy('siswa_id')
            ->selectRaw('siswa_id, sum(poin) as jumlah')
            ->get()
            ->each(function ($r) use (&$out) {
                $out[(int) $r->siswa_id] = max(0, (int) $r->jumlah);
            });

        return $out;
    }

    public static function namaTampil(Siswa $s): string
    {
        return $s->nama_panggilan ?: (string) strtok($s->nama, ' ');
    }

    /** Papan semua siswa aktif, untuk guru: poin tertinggi dulu. */
    public static function papan(Kelas $kelas, GamifikasiPengaturan $p): array
    {
        $total = self::total($kelas, $p->mulai_pada->toDateString());
        $siswas = Siswa::where('kelas_id', $kelas->id)->where('aktif', true)->orderBy('nama')->get();
        $lencana = DB::table('lencana_siswas')
            ->whereIn('siswa_id', $siswas->pluck('id'))
            ->groupBy('siswa_id')
            ->selectRaw('siswa_id, count(*) as n')
            ->pluck('n', 'siswa_id');
        $terakhir = DB::table('poin_siswas')
            ->where('kelas_id', $kelas->id)
            ->where('terjadi_pada', '>=', $p->mulai_pada->toDateString())
            ->groupBy('siswa_id')
            ->selectRaw('siswa_id, max(terjadi_pada) as t')
            ->pluck('t', 'siswa_id');

        $baris = $siswas->map(fn (Siswa $s) => [
            'siswa'    => $s,
            'total'    => $total[$s->id] ?? 0,
            'lencana'  => (int) ($lencana[$s->id] ?? 0),
            'terakhir' => $terakhir[$s->id] ?? null,
        ])->all();

        usort($baris, fn ($a, $b) => [$b['total'], $a['siswa']->nama] <=> [$a['total'], $b['siswa']->nama]);

        return self::beriPeringkat($baris, fn ($b) => $b['total']);
    }

    /**
     * Papan tim dari sesi kelompok (Fase 2) yang dipilih guru. Skor tim = rata-rata poin per anggota aktif
     * sejak awal putaran, supaya tim yang anggotanya lebih banyak satu orang tidak otomatis menang.
     *
     * @return array<int,array{id:int,nama:string,jumlah:int,total:int,rata:float,peringkat:int,anggota:array}>
     */
    public static function tim(Kelas $kelas, GamifikasiPengaturan $p): array
    {
        if (! $p->kelompok_sesi_id) {
            return [];
        }

        $sesi = DB::table('kelompok_sesis')
            ->where('id', $p->kelompok_sesi_id)
            ->where('kelas_id', $kelas->id)
            ->whereNull('deleted_at')
            ->first();
        if (! $sesi) {
            return [];
        }

        $poin = self::total($kelas, $p->awalTim());
        $timDb = DB::table('kelompoks')->where('sesi_id', $sesi->id)->orderBy('urutan')->orderBy('id')->get();
        $anggota = DB::table('kelompok_anggotas as a')
            ->join('siswas as s', 's.id', '=', 'a.siswa_id')
            ->whereIn('a.kelompok_id', $timDb->pluck('id'))
            ->where('s.kelas_id', $kelas->id)
            ->where('s.aktif', true)
            ->whereNull('s.deleted_at')
            ->orderBy('s.nama')
            ->get(['a.kelompok_id', 's.id as siswa_id', 's.nama', 's.nama_panggilan'])
            ->groupBy('kelompok_id');

        $hasil = [];
        foreach ($timDb as $k) {
            $isi = $anggota->get($k->id, collect());
            if ($isi->isEmpty()) {
                continue;
            }

            $daftar = $isi->map(fn ($a) => [
                'id'   => (int) $a->siswa_id,
                'nama' => $a->nama_panggilan ?: (string) strtok($a->nama, ' '),
                'poin' => $poin[(int) $a->siswa_id] ?? 0,
            ])->all();

            $total = array_sum(array_column($daftar, 'poin'));
            $hasil[] = [
                'id'      => (int) $k->id,
                'nama'    => $k->nama,
                'jumlah'  => count($daftar),
                'total'   => $total,
                'rata'    => round($total / count($daftar), 1),
                'anggota' => $daftar,
            ];
        }

        usort($hasil, fn ($a, $b) => [$b['rata'], $b['total'], $a['nama']] <=> [$a['rata'], $b['total'], $b['nama']]);

        return self::beriPeringkat($hasil, fn ($t) => [$t['rata'], $t['total']]);
    }

    /** Target bersama kelas, atau null kalau tidak diatur. */
    public static function target(Kelas $kelas, GamifikasiPengaturan $p): ?array
    {
        if (! $p->target_poin) {
            return null;
        }

        $capai = array_sum(self::total($kelas, $p->awalTarget()));

        return [
            'judul'    => $p->target_judul,
            'target'   => (int) $p->target_poin,
            'capai'    => $capai,
            'persen'   => (int) min(100, floor($capai * 100 / $p->target_poin)),
            'tercapai' => $capai >= $p->target_poin,
        ];
    }

    /** Peringkat gaya lomba: nilai sama = peringkat sama, peringkat berikutnya melompat. */
    private static function beriPeringkat(array $urut, callable $kunci): array
    {
        $rank = 0;
        $prev = null;

        foreach ($urut as $i => &$baris) {
            $k = $kunci($baris);
            if ($i === 0 || $k !== $prev) {
                $rank = $i + 1;
            }
            $baris['peringkat'] = $rank;
            $prev = $k;
        }

        return $urut;
    }

    // ------------------------------------------------------------------ untuk siswa

    /** Ringkasan kecil untuk kartu di Dashboard Saya. Null bila gamifikasi kelas ini mati. */
    public static function ringkas(Siswa $siswa): ?array
    {
        $p = GamifikasiPengaturan::where('kelas_id', $siswa->kelas_id)->where('aktif', true)->first();
        if (! $p) {
            return null;
        }

        $kelas = $siswa->kelas;
        self::sinkron($kelas);
        $total = self::total($kelas, $p->mulai_pada->toDateString());

        return [
            'total'   => $total[$siswa->id] ?? 0,
            'lencana' => (int) DB::table('lencana_siswas')->where('siswa_id', $siswa->id)->count(),
            'target'  => self::target($kelas, $p),
        ];
    }

    /** Semua bahan halaman Poin milik satu siswa. Hanya data siswa itu sendiri dan data yang memang boleh dilihat teman sekelas. */
    public static function untukSiswa(Siswa $siswa, GamifikasiPengaturan $p): array
    {
        $kelas = $siswa->kelas;
        $sejak = $p->mulai_pada->toDateString();
        $total = self::total($kelas, $sejak);
        $diraih = DB::table('lencana_siswas')->where('siswa_id', $siswa->id)->pluck('diraih_pada', 'kode');

        $lencana = [];
        foreach (self::LENCANA as $kode => [$nama, $syarat, $ikon]) {
            $lencana[] = [
                'kode'   => $kode,
                'nama'   => $nama,
                'syarat' => $syarat,
                'ikon'   => $ikon,
                'diraih' => $diraih->has($kode) ? Carbon::parse($diraih[$kode]) : null,
            ];
        }

        $riwayat = DB::table('poin_siswas')
            ->where('siswa_id', $siswa->id)
            ->where('terjadi_pada', '>=', $sejak)
            ->orderByDesc('terjadi_pada')
            ->orderByDesc('id')
            ->limit(15)
            ->get(['jenis', 'poin', 'keterangan', 'terjadi_pada'])
            ->map(fn ($r) => [
                'label'      => self::JENIS[$r->jenis] ?? $r->jenis,
                'poin'       => (int) $r->poin,
                'keterangan' => $r->keterangan,
                'tanggal'    => Carbon::parse($r->terjadi_pada),
            ])->all();

        $tim = self::tim($kelas, $p);
        $timku = collect($tim)->first(fn ($t) => collect($t['anggota'])->contains('id', $siswa->id));

        $lima = [];
        if ($p->papan_individu) {
            $semua = self::papan($kelas, $p);
            $lima = collect($semua)
                ->filter(fn ($b) => $b['total'] > 0)
                ->take(5)
                ->map(fn ($b) => [
                    'nama'      => self::namaTampil($b['siswa']),
                    'total'     => $b['total'],
                    'peringkat' => $b['peringkat'],
                    'saya'      => $b['siswa']->id === $siswa->id,
                ])->values()->all();
        }

        return [
            'total'   => $total[$siswa->id] ?? 0,
            'lencana' => $lencana,
            'riwayat' => $riwayat,
            'tim'     => $tim,
            'timku'   => $timku ? $timku['id'] : null,
            'target'  => self::target($kelas, $p),
            'lima'    => $lima,
        ];
    }
}