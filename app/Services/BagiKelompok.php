<?php

namespace App\Services;

use App\Models\Kelompok;
use App\Models\KelompokSesi;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BagiKelompok
{
    /**
     * @param string $metode 'acak' atau 'campur_gender'
     */
    public static function jalankan(
        KelompokSesi $sesi,
        string $metode = 'acak',
        bool $hindariUlang = true,
        bool $acakKetua = true
    ): void {
        $kelompoks = $sesi->kelompoks()->with('anggotas')->orderBy('urutan')->get();

        if ($kelompoks->isEmpty()) {
            throw new RuntimeException('Belum ada kelompok. Tambahkan kelompok dulu.');
        }

        $siswas = $sesi->kelas->siswas()->where('aktif', true)->get(['id', 'gender'])->keyBy('id');

        if ($siswas->isEmpty()) {
            throw new RuntimeException('Kelas ini belum punya siswa aktif.');
        }

        // id siswa => 'L' / 'P' / 'X' (tidak diketahui)
        $gender = $siswas->map(fn ($s) => $s->gender ?: 'X')->all();

        // Siswa yang dikunci
        $kunci = [];
        $idKunci = [];

        foreach ($kelompoks as $k) {
            $kunci[$k->id] = [];

            foreach ($k->anggotas as $a) {
                if ($a->terkunci && isset($siswas[$a->siswa_id])) {
                    $kunci[$k->id][] = (int) $a->siswa_id;
                    $idKunci[] = (int) $a->siswa_id;
                }
            }
        }

        $semuaId = $siswas->keys()->map(fn ($v) => (int) $v)->all();
        $pool = array_values(array_diff($semuaId, $idKunci));
        $idKel = $kelompoks->pluck('id')->all();
        $pasanganLama = $hindariUlang ? self::pasanganLama($sesi) : [];
        $perGender = array_count_values($gender);

        $terbaik = null;
        $skorTerbaik = PHP_INT_MAX;

        for ($i = 0; $i < 300; $i++) {
            $anggota = $kunci; // id_kelompok => [id_siswa...]
            $p = $pool;
            shuffle($p);

            if ($metode === 'campur_gender') {
                // Kelompokkan per gender (L, P, X); urutan acak dalam gender dipertahankan
                usort($p, fn ($a, $b) => strcmp($gender[$a], $gender[$b]));
            }

            foreach ($p as $sid) {
                $kandidat = $idKel;
                shuffle($kandidat);

                $pilih = $kandidat[0];
                $ukuran = count($anggota[$pilih]);
                $gPilih = $metode === 'campur_gender' ? self::hitung($anggota[$pilih], $gender, $gender[$sid]) : 0;

                foreach ($kandidat as $kid) {
                    $u = count($anggota[$kid]);
                    $g = $metode === 'campur_gender' ? self::hitung($anggota[$kid], $gender, $gender[$sid]) : 0;

                    if ($u < $ukuran || ($u === $ukuran && $g < $gPilih)) {
                        $pilih = $kid;
                        $ukuran = $u;
                        $gPilih = $g;
                    }
                }

                $anggota[$pilih][] = $sid;
            }

            $skor = self::skor($anggota, $gender, $perGender, $pasanganLama, $metode);

            if ($skor < $skorTerbaik) {
                $skorTerbaik = $skor;
                $terbaik = $anggota;
            }

            if ($skor === 0) {
                break;
            }
        }

        DB::transaction(function () use ($kelompoks, $terbaik, $kunci, $acakKetua) {
            foreach ($kelompoks as $k) {
                // Hapus anggota yang tidak dikunci, lalu isi ulang hasil acak
                $k->anggotas()->where('terkunci', false)->delete();

                foreach ($terbaik[$k->id] as $sid) {
                    if (in_array($sid, $kunci[$k->id], true)) {
                        continue; // Yang dikunci sudah ada di database
                    }

                    $k->anggotas()->create(['siswa_id' => $sid, 'terkunci' => false]);
                }

                $semua = $terbaik[$k->id];
                $adaKetua = in_array((int) $k->ketua_siswa_id, $semua, true);

                if ($acakKetua || ! $adaKetua) {
                    $k->update(['ketua_siswa_id' => $semua ? $semua[array_rand($semua)] : null]);
                }
            }
        });
    }

    private static function hitung(array $ids, array $gender, string $g): int
    {
        return count(array_filter($ids, fn ($x) => $gender[$x] === $g));
    }

    private static function skor(array $anggota, array $gender, array $perGender, array $pasanganLama, string $metode): int
    {
        $skor = 0;

        // Penalti: pasangan yang sudah sekelompok di sesi sebelumnya
        foreach ($anggota as $daftar) {
            $d = $daftar;
            sort($d);
            $n = count($d);

            for ($a = 0; $a < $n; $a++) {
                for ($b = $a + 1; $b < $n; $b++) {
                    $skor += $pasanganLama[$d[$a] . '-' . $d[$b]] ?? 0;
                }
            }
        }

        // Penalti: ketidakmerataan gender
        if ($metode === 'campur_gender') {
            $jumlah = count($anggota);

            foreach (['L', 'P'] as $g) {
                $rata = ($perGender[$g] ?? 0) / $jumlah;

                foreach ($anggota as $daftar) {
                    $skor += (int) round((self::hitung($daftar, $gender, $g) - $rata) ** 2 * 2);
                }
            }
        }

        return $skor;
    }

    // Pasangan siswa yang pernah sekelompok di 3 sesi terakhir kelas ini
    private static function pasanganLama(KelompokSesi $sesi): array
    {
        $idSesi = KelompokSesi::where('kelas_id', $sesi->kelas_id)
            ->where('id', '!=', $sesi->id)
            ->latest('id')
            ->take(3)
            ->pluck('id');

        $peta = [];

        Kelompok::whereIn('sesi_id', $idSesi)->with('anggotas')->get()->each(function ($k) use (&$peta) {
            $ids = $k->anggotas->pluck('siswa_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
            $n = count($ids);

            for ($a = 0; $a < $n; $a++) {
                for ($b = $a + 1; $b < $n; $b++) {
                    $kunci = $ids[$a] . '-' . $ids[$b];
                    $peta[$kunci] = ($peta[$kunci] ?? 0) + 1;
                }
            }
        });

        return $peta;
    }
}