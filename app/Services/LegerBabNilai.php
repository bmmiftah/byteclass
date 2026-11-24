<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\LegerBab;
use App\Models\LegerPengaturan;
use App\Models\NilaiSiswa;
use App\Models\Penilaian;

/**
 * Leger per bab (Pengetahuan dan Keterampilan).
 *
 * Alur: nilai slot -> n-KD (rata-rata berbobot slot dalam satu aspek di satu bab, DIBULATKAN)
 *                     -> nilai aspek = rata-rata n-KD semua bab (sama rata)
 *                     -> Pengetahuan boleh digabung dengan PTS dan PAS (di luar bab, bobot diatur guru).
 * Slot atau bab yang kosong dilewati (bobot sisanya dihitung ulang) dan ditandai "belum lengkap".
 * Sumber nilai tetap tabel Penilaian; slot hanya menunjuk penilaian mana yang mengisinya.
 */
class LegerBabNilai
{
    public const ASPEK = ['pengetahuan' => 'Pengetahuan', 'keterampilan' => 'Keterampilan'];

    public static function pengaturan(Kelas $kelas, int $mapelId): array
    {
        $p = LegerPengaturan::where('kelas_id', $kelas->id)->where('mapel_id', $mapelId)->first();

        return LegerNilai::pengaturan($kelas, $mapelId) + [
            'mode'      => $p?->mode === 'bab' ? 'bab' : 'kategori',
            'pts_id'    => $p?->pts_penilaian_id ? (int) $p->pts_penilaian_id : null,
            'pas_id'    => $p?->pas_penilaian_id ? (int) $p->pas_penilaian_id : null,
            'bobot_nkd' => (int) ($p?->bobot_nkd ?? 50),
            'bobot_pts' => (int) ($p?->bobot_pts ?? 25),
            'bobot_pas' => (int) ($p?->bobot_pas ?? 25),
        ];
    }

    public static function modeBab(Kelas $kelas, int $mapelId): bool
    {
        return LegerPengaturan::where('kelas_id', $kelas->id)->where('mapel_id', $mapelId)->value('mode') === 'bab';
    }

    /** Pembulatan ke bilangan bulat, .5 naik. */
    public static function bulat(float $v): int
    {
        return (int) round(round($v, 6));
    }

    /** Rata-rata berbobot dari pasangan [nilai, bobot]; null kalau tidak ada yang berbobot. */
    public static function rataBerbobot(array $pasangan): ?float
    {
        $atas = 0.0;
        $bawah = 0;

        foreach ($pasangan as [$nilai, $bobot]) {
            if ($bobot > 0) {
                $atas += $nilai * $bobot;
                $bawah += $bobot;
            }
        }

        return $bawah > 0 ? $atas / $bawah : null;
    }

    public static function babs(Kelas $kelas, int $mapelId)
    {
        return LegerBab::with(['slots.penilaian'])
            ->where('kelas_id', $kelas->id)->where('mapel_id', $mapelId)
            ->orderBy('urutan')->orderBy('id')->get();
    }

    /**
     * @return array{
     *     pengaturan: array, babs: \Illuminate\Support\Collection, aspekAktif: array<string,bool>,
     *     pts: ?Penilaian, pas: ?Penilaian, baris: array, statistik: array, slotKosong: array
     * }
     */
    public static function hitung(Kelas $kelas, int $mapelId): array
    {
        $set = self::pengaturan($kelas, $mapelId);
        $babs = self::babs($kelas, $mapelId);
        $siswas = $kelas->siswas()->where('aktif', true)->orderBy('nama')->get();

        $pts = $set['pts_id'] ? Penilaian::where('kelas_id', $kelas->id)->find($set['pts_id']) : null;
        $pas = $set['pas_id'] ? Penilaian::where('kelas_id', $kelas->id)->find($set['pas_id']) : null;

        $ids = [];
        foreach ($babs as $b) {
            foreach ($b->slots as $s) {
                if ($s->penilaian) {
                    $ids[] = $s->penilaian->id;
                }
            }
        }
        foreach ([$pts, $pas] as $x) {
            if ($x) {
                $ids[] = $x->id;
            }
        }

        $nilai = NilaiSiswa::whereIn('penilaian_id', array_unique($ids))->whereNotNull('total')->get()
            ->groupBy('siswa_id')->map(fn ($g) => $g->mapWithKeys(fn ($n) => [$n->penilaian_id => (float) $n->total]));

        // Aspek yang tampil: ada bab yang menyalakannya (Pengetahuan juga kalau PTS/PAS dipakai)
        $aktif = [
            'pengetahuan'  => $babs->contains(fn ($b) => $b->pengetahuan) || $pts || $pas,
            'keterampilan' => $babs->contains(fn ($b) => $b->keterampilan),
        ];

        $slotKosong = [];
        foreach ($babs as $b) {
            foreach ($b->slots as $s) {
                $nyala = $b->{$s->aspek};
                if ($nyala && ! $s->penilaian) {
                    $slotKosong[] = [
                        'bab'   => $b->nama,
                        'aspek' => self::ASPEK[$s->aspek] ?? $s->aspek,
                        'slot'  => $s->nama,
                    ];
                }
            }
        }

        $baris = [];
        foreach ($siswas as $siswa) {
            $punya = $nilai->get($siswa->id, collect());
            $nkd = [];
            $slotNil = [];
            $kurang = ['pengetahuan' => 0, 'keterampilan' => 0];

            foreach ($babs as $b) {
                foreach (array_keys(self::ASPEK) as $aspek) {
                    if (! $b->{$aspek}) {
                        continue;
                    }

                    $slots = $b->slots->where('aspek', $aspek);
                    $pasangan = [];

                    foreach ($slots as $s) {
                        $v = $s->penilaian ? $punya->get($s->penilaian->id) : null;
                        $slotNil[$s->id] = $v;
                        if ($v !== null) {
                            $pasangan[] = [$v, (int) $s->bobot];
                        }
                    }

                    $kurang[$aspek] += max(1, $slots->count()) - count($pasangan); // bab tanpa slot dihitung 1 yang kurang
                    $r = self::rataBerbobot($pasangan);
                    $nkd[$b->id][$aspek] = $r === null ? null : self::bulat($r);
                }
            }

            $nilaiAspek = [];
            $ptsNilai = $pts ? $punya->get($pts->id) : null;
            $pasNilai = $pas ? $punya->get($pas->id) : null;

            foreach (array_keys(self::ASPEK) as $aspek) {
                $vals = [];
                foreach ($nkd as $perBab) {
                    if (($perBab[$aspek] ?? null) !== null) {
                        $vals[] = $perBab[$aspek];
                    }
                }

                $rata = $vals ? array_sum($vals) / count($vals) : null;

                if ($aspek === 'pengetahuan') {
                    $komp = [];
                    if ($rata !== null) {
                        $komp[] = [$rata, $set['bobot_nkd']];
                    }
                    if ($pts) {
                        $ptsNilai !== null ? $komp[] = [$ptsNilai, $set['bobot_pts']] : $kurang[$aspek]++;
                    }
                    if ($pas) {
                        $pasNilai !== null ? $komp[] = [$pasNilai, $set['bobot_pas']] : $kurang[$aspek]++;
                    }
                    $r = self::rataBerbobot($komp);
                } else {
                    $r = $rata;
                }

                $nilaiAspek[$aspek] = $r === null ? null : self::bulat($r);
            }

            $baris[] = [
                'siswa'     => $siswa,
                'slot'      => $slotNil,
                'nkd'       => $nkd,
                'pts'       => $ptsNilai,
                'pas'       => $pasNilai,
                'nilai'     => $nilaiAspek,
                'predikat'  => array_map(fn ($v) => LegerNilai::predikat($v, $set['kkm']), $nilaiAspek),
                'kurang'    => $kurang,
                'sementara' => array_sum($kurang) > 0,
            ];
        }

        $statistik = [];
        foreach (array_keys(self::ASPEK) as $aspek) {
            $v = array_values(array_filter(array_map(fn ($b) => $b['nilai'][$aspek], $baris), fn ($x) => $x !== null));
            $statistik[$aspek] = [
                'ada'      => count($v),
                'rata'     => $v ? round(array_sum($v) / count($v), 1) : null,
                'tuntas'   => count(array_filter($v, fn ($x) => $x >= $set['kkm'])),
                'remedial' => count(array_filter($v, fn ($x) => $x < $set['kkm'])),
            ];
        }
        $statistik['siswa'] = count($baris);
        $statistik['sementara'] = count(array_filter($baris, fn ($b) => $b['sementara']));

        return compact('babs', 'aktif', 'pts', 'pas', 'baris', 'statistik', 'slotKosong') + [
            'pengaturan' => $set,
            'aspekAktif' => $aktif,
        ];
    }

    /** @return array{0: array<int,string>, 1: array<int,array>} */
    public static function tabelExport(array $h): array
    {
        $judul = ['No', 'NIS', 'Nama'];

        foreach ($h['babs'] as $b) {
            foreach (array_keys(self::ASPEK) as $aspek) {
                if (! $b->{$aspek}) {
                    continue;
                }
                foreach ($b->slots->where('aspek', $aspek) as $s) {
                    $judul[] = $b->nama . ' - ' . self::ASPEK[$aspek] . ' - ' . $s->nama . ' (' . $s->bobot . '%)';
                }
                $judul[] = $b->nama . ' - ' . self::ASPEK[$aspek] . ' - n-KD';
            }
        }

        if ($h['pts']) {
            $judul[] = 'PTS';
        }
        if ($h['pas']) {
            $judul[] = 'PAS';
        }

        foreach (array_keys(self::ASPEK) as $aspek) {
            if ($h['aspekAktif'][$aspek]) {
                array_push($judul, 'Nilai Rapor ' . self::ASPEK[$aspek], 'Predikat ' . self::ASPEK[$aspek]);
            }
        }
        $judul[] = 'Keterangan';

        $baris = [];
        foreach ($h['baris'] as $i => $r) {
            $row = [$i + 1, $r['siswa']->nis, $r['siswa']->nama];

            foreach ($h['babs'] as $b) {
                foreach (array_keys(self::ASPEK) as $aspek) {
                    if (! $b->{$aspek}) {
                        continue;
                    }
                    foreach ($b->slots->where('aspek', $aspek) as $s) {
                        $row[] = $r['slot'][$s->id] ?? '';
                    }
                    $row[] = $r['nkd'][$b->id][$aspek] ?? '';
                }
            }

            if ($h['pts']) {
                $row[] = $r['pts'] ?? '';
            }
            if ($h['pas']) {
                $row[] = $r['pas'] ?? '';
            }

            foreach (array_keys(self::ASPEK) as $aspek) {
                if ($h['aspekAktif'][$aspek]) {
                    array_push($row, $r['nilai'][$aspek] ?? '', $r['predikat'][$aspek]);
                }
            }

            $row[] = $r['sementara'] ? 'Belum lengkap' : 'Lengkap';
            $baris[] = $row;
        }

        return [$judul, $baris];
    }
}