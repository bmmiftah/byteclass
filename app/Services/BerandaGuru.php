<?php

namespace App\Services;

use App\Models\Agenda;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bahan untuk Beranda Guru: ringkasan "hari ini" lintas semua kelas aktif milik satu guru.
 * Semua query dibatasi guru_id lewat daftar kelasnya, jadi tidak ada data guru lain yang bisa ikut.
 */
class BerandaGuru
{
    public const HARI_DEPAN = 7;
    public const BATAS_ALPA = 3; // alpa minimal sekian kali ...
    public const JENDELA_ALPA = 14; // ... dalam sekian hari terakhir = perlu perhatian
    public const JENDELA_REMEDIAL = 45; // ujian yang masih dianggap baru untuk remedial (hari)

    public static function sapaan(Carbon $sekarang): string
    {
        $j = (int) $sekarang->format('G');

        return match (true) {
            $j < 4  => 'Selamat malam',
            $j < 11 => 'Selamat pagi',
            $j < 15 => 'Selamat siang',
            $j < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    public static function data(User $guru, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? now())->copy();
        $hari = $sekarang->copy()->startOfDay();
        $minggu = $sekarang->isSunday();

        $kelas = Kelas::where('guru_id', $guru->id)->where('arsip', false)->orderBy('nama')->get();
        $kelasIds = $kelas->pluck('id')->all();
        $nama = $kelas->pluck('nama', 'id');

        $siswaAktif = Siswa::whereIn('kelas_id', $kelasIds)->where('aktif', true)->get(['id', 'kelas_id', 'nama']);
        $perKelas = $siswaAktif->groupBy('kelas_id')->map->count();

        // ---- tugas yang sudah dikumpulkan tapi belum dinilai ----
        $perluDinilai = Tugas::whereIn('kelas_id', $kelasIds)->whereIn('status', ['terbit', 'arsip'])
            ->withCount(['pengumpulans as belum_dinilai' => fn ($q) => $q->whereNull('nilai')->whereNotNull('dikirim_pada')])
            ->orderBy('tenggat')->get()
            ->filter(fn ($t) => $t->belum_dinilai > 0)->values()
            ->map(fn ($t) => ['tugas' => $t, 'kelas' => $nama[$t->kelas_id] ?? '-', 'jumlah' => (int) $t->belum_dinilai]);

        // ---- ujian: uraian yang menunggu koreksi ----
        $perluKoreksi = Ujian::whereIn('kelas_id', $kelasIds)->where('status', '!=', 'draft')
            ->withCount(['pengerjaans as menunggu' => fn ($q) => $q->where('status', 'selesai')->where('perlu_koreksi', true)])
            ->orderByDesc('id')->get()
            ->filter(fn ($u) => $u->menunggu > 0)->values()
            ->map(fn ($u) => ['ujian' => $u, 'kelas' => $nama[$u->kelas_id] ?? '-', 'jumlah' => (int) $u->menunggu]);

        // ---- ujian yang sedang dibuka dan yang akan dibuka ----
        $ujianBuka = Ujian::whereIn('kelas_id', $kelasIds)->where('status', 'buka')
            ->where(fn ($q) => $q->whereNull('buka_pada')->orWhere('buka_pada', '<=', $sekarang))
            ->where(fn ($q) => $q->whereNull('tutup_pada')->orWhere('tutup_pada', '>=', $sekarang))
            ->withCount([
                'pengerjaans as berjalan' => fn ($q) => $q->where('status', 'berjalan'),
                'pengerjaans as selesai'  => fn ($q) => $q->where('status', 'selesai'),
            ])->orderBy('id')->get()
            ->map(fn ($u) => ['ujian' => $u, 'kelas' => $nama[$u->kelas_id] ?? '-', 'sasaran' => $u->siswaPeserta()->count()]);

        $ujianAkan = Ujian::whereIn('kelas_id', $kelasIds)->where('status', 'buka')
            ->where('buka_pada', '>', $sekarang)->where('buka_pada', '<=', $sekarang->copy()->addDays(self::HARI_DEPAN))
            ->orderBy('buka_pada')->get()
            ->map(fn ($u) => ['ujian' => $u, 'kelas' => $nama[$u->kelas_id] ?? '-']);

        // ---- tugas yang masih berjalan, dengan kemajuan pengumpulan ----
        $tugasBerjalan = Tugas::terlihat()->whereIn('kelas_id', $kelasIds)->where('status', 'terbit')
            ->where('tenggat', '>=', $sekarang)->withCount('pengumpulans')->orderBy('tenggat')->get()
            ->map(fn ($t) => ['tugas' => $t, 'kelas' => $nama[$t->kelas_id] ?? '-', 'sasaran' => (int) ($perKelas[$t->kelas_id] ?? 0)]);

        // ---- presensi hari ini yang belum diisi (lewati hari Minggu dan kelas yang sedang libur) ----
        $presensiBelum = collect();
        if (! $minggu && $kelasIds) {
            $sudah = Presensi::whereIn('kelas_id', $kelasIds)->whereDate('tanggal', $hari)->pluck('kelas_id')->unique()->all();
            $libur = Agenda::whereIn('kelas_id', $kelasIds)->where('jenis', 'libur')
                ->where('mulai', '<=', $hari->copy()->endOfDay())->get()
                ->filter(fn ($a) => $a->akhir->copy()->endOfDay()->gte($hari))->pluck('kelas_id')->unique()->all();
            $presensiBelum = $kelas->filter(fn ($k) => ($perKelas[$k->id] ?? 0) > 0
                && ! in_array($k->id, $sudah, true) && ! in_array($k->id, $libur, true))->values();
        }

        // ---- jurnal mengajar hari ini belum ditulis ----
        $jurnalBelum = ! $minggu && $kelasIds && ! Jurnal::where('guru_id', $guru->id)->whereDate('tanggal', $hari)->exists();

        // ---- agenda, tenggat, dan jadwal ujian 7 hari ke depan ----
        $agenda = collect();
        foreach ($kelas as $k) {
            foreach (KalenderKelas::kejadian($k, $hari, $hari->copy()->addDays(self::HARI_DEPAN - 1)) as $e) {
                $agenda->push($e + ['kelas' => $k->nama]);
            }
        }
        $agenda = $agenda->sortBy(fn ($e) => $e['tanggal'] . ($e['jam'] === null ? '0' : '1') . $e['jam'] . $e['kelas'])->values();

        // ---- ujian selesai dengan siswa di bawah KKM yang belum dibuatkan remedial ----
        $remedial = collect();
        $calon = Ujian::whereIn('kelas_id', $kelasIds)->whereNull('remedial_dari_id')->whereIn('status', ['buka', 'tutup'])
            ->where('updated_at', '>=', $sekarang->copy()->subDays(self::JENDELA_REMEDIAL))
            ->whereDoesntHave('remedials')->whereHas('pengerjaans', fn ($q) => $q->where('status', 'selesai'))
            ->orderByDesc('id')->limit(20)->get();
        foreach ($calon as $u) {
            $kkm = LegerNilai::pengaturan($kelas->firstWhere('id', $u->kelas_id), (int) ($u->mapel_id ?? 0))['kkm'];
            $n = $u->pengerjaans()->where('status', 'selesai')->where('perlu_koreksi', false)->where('nilai', '<', $kkm)->count();
            if ($n > 0) {
                $remedial->push(['ujian' => $u, 'kelas' => $nama[$u->kelas_id] ?? '-', 'jumlah' => $n, 'kkm' => $kkm]);
            }
        }

        // ---- siswa yang sering alpa ----
        $alpa = Presensi::whereIn('kelas_id', $kelasIds)->where('status', 'A')
            ->whereDate('tanggal', '>=', $hari->copy()->subDays(self::JENDELA_ALPA - 1))
            ->selectRaw('siswa_id, count(*) as jml')->groupBy('siswa_id')->havingRaw('count(*) >= ?', [self::BATAS_ALPA])
            ->orderByDesc('jml')->get();
        $siswaAlpa = $alpa->map(function ($r) use ($siswaAktif, $nama) {
            $s = $siswaAktif->firstWhere('id', $r->siswa_id);

            return $s ? ['siswa' => $s, 'kelas' => $nama[$s->kelas_id] ?? '-', 'jumlah' => (int) $r->jml] : null;
        })->filter()->values();

        $ringkas = [
            'kelas'     => $kelas->count(),
            'siswa'     => $siswaAktif->count(),
            'tugas'     => $tugasBerjalan->count(),
            'ujianBuka' => $ujianBuka->count(),
        ];

        $perluTindakan = $presensiBelum->count() + $perluDinilai->count() + $perluKoreksi->count() + $remedial->count();

        return compact(
            'sekarang',
            'kelas',
            'ringkas',
            'perluDinilai',
            'perluKoreksi',
            'ujianBuka',
            'ujianAkan',
            'tugasBerjalan',
            'presensiBelum',
            'jurnalBelum',
            'agenda',
            'remedial',
            'siswaAlpa',
            'perluTindakan',
            'minggu'
        );
    }
}