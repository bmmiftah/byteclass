<?php

namespace App\Services;

use App\Models\Denah;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AcakDenah
{
    public static function jalankan(Denah $denah, bool $hindariSama = true): void
    {
        $mejas = $denah->mejas()->get();
        $siswas = $denah->kelas->siswas()->where('aktif', true)->pluck('id')->all();

        $terkunci = $mejas->where('terkunci', true);
        $bebas = $mejas->where('terkunci', false)->values();

        if ($bebas->isEmpty()) {
            throw new RuntimeException('Tidak ada meja bebas untuk diacak.');
        }

        // Siswa yang sudah dikunci di mejanya tidak ikut diacak
        $idDikunci = $terkunci->pluck('siswa_id')->filter()->map(fn ($v) => (int) $v)->all();
        $pool = array_values(array_diff($siswas, $idDikunci));

        if (count($pool) > $bebas->count()) {
            throw new RuntimeException(
                'Meja kurang: ada ' . count($pool) . ' siswa yang perlu diacak, tetapi hanya '
                . $bebas->count() . ' meja bebas. Tambah meja atau kurangi meja yang dikunci.'
            );
        }

        $pasangan = $denah->aturan()->get()->map(fn ($a) => [(int) $a->siswa_a, (int) $a->siswa_b])->all();
        $sebelumnya = $bebas->mapWithKeys(fn ($m) => [$m->id => (int) $m->siswa_id])->all();
        $koordinat = $bebas->mapWithKeys(fn ($m) => [$m->id => [$m->x, $m->y]])->all();
        $idBebas = $bebas->pluck('id')->all();

        $posisiKunci = [];
        foreach ($terkunci as $m) {
            if ($m->siswa_id) {
                $posisiKunci[(int) $m->siswa_id] = [$m->x, $m->y];
            }
        }

        $terbaik = null;
        $skorTerbaik = PHP_INT_MAX;

        for ($i = 0; $i < 400; $i++) {
            $acakSiswa = $pool;
            $acakMeja = $idBebas;

            shuffle($acakSiswa);
            shuffle($acakMeja);

            $hasil = []; // id_meja => id_siswa
            foreach ($acakSiswa as $n => $sid) {
                $hasil[$acakMeja[$n]] = $sid;
            }

            // Posisi semua siswa pada acakan ini
            $pos = $posisiKunci;
            foreach ($hasil as $mid => $sid) {
                $pos[$sid] = $koordinat[$mid];
            }

            $skor = 0;

            // Aturan pisah: dilanggar jika bersebelahan (termasuk diagonal)
            foreach ($pasangan as [$a, $b]) {
                if (isset($pos[$a], $pos[$b])) {
                    $jarak = max(abs($pos[$a][0] - $pos[$b][0]), abs($pos[$a][1] - $pos[$b][1]));
                    if ($jarak <= 1) {
                        $skor += 100;
                    }
                }
            }

            // Hindari duduk di meja yang sama dengan acakan sebelumnya
            if ($hindariSama) {
                foreach ($hasil as $mid => $sid) {
                    if (($sebelumnya[$mid] ?? 0) === $sid) {
                        $skor++;
                    }
                }
            }

            if ($skor < $skorTerbaik) {
                $skorTerbaik = $skor;
                $terbaik = $hasil;
            }

            if ($skor === 0) {
                break;
            }
        }

        DB::transaction(function () use ($bebas, $terbaik, $denah) {
            foreach ($bebas as $m) {
                $m->update(['siswa_id' => $terbaik[$m->id] ?? null]);
            }

            $denah->riwayat()->create([
                'snapshot' => $denah->mejas()->get(['id', 'x', 'y', 'siswa_id'])->toArray(),
            ]);
        });
    }
}