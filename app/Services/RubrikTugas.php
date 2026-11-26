<?php

namespace App\Services;

use App\Models\Penilaian;
use App\Models\Rubrik;
use App\Models\Tugas;
use App\Models\TugasKriteria;
use App\Models\TugasNilaiKriteria;
use App\Models\TugasPengumpulan;
use Illuminate\Support\Facades\DB;

/**
 * Menilai tugas dengan rubrik (Fase 9D).
 * Rubrik dari pustaka DISALIN ke tugas (snapshot), nilai tiap kriteria disimpan, lalu total dihitung dengan
 * rumus Fase 3 (HitungNilai) dan ditulis ke tugas_pengumpulans.nilai. Semua yang membaca nilai tugas
 * (kirim nilai, Leger, tampilan siswa) tetap memakai kolom nilai itu.
 */
class RubrikTugas
{
    /** Salin kriteria rubrik ke tugas. Mengembalikan jumlah kriteria, atau null kalau rubrik kosong. */
    public static function pasang(Tugas $tugas, Rubrik $rubrik): ?int
    {
        $kriterias = $rubrik->kriterias()->get();
        if ($kriterias->isEmpty()) {
            return null;
        }

        DB::transaction(function () use ($tugas, $kriterias) {
            $tugas->kriterias()->delete();
            foreach ($kriterias as $i => $k) {
                TugasKriteria::create([
                    'tugas_id' => $tugas->id, 'nama' => $k->nama, 'tipe' => $k->tipe,
                    'maks' => $k->maks, 'bobot' => $k->bobot, 'urutan' => $i,
                ]);
            }
        });

        return $kriterias->count();
    }

    /** Lepas rubrik: kriteria dan nilai per kriteria dihapus; nilai total siswa tetap tersimpan. */
    public static function lepas(Tugas $tugas): void
    {
        $tugas->kriterias()->delete();
    }

    /**
     * Simpan nilai per kriteria satu pengumpulan.
     * $input: [tugas_kriteria_id => nilai mentah dari form] ('' = belum diisi).
     * Semua kosong = hanya verifikasi (nilai dikosongkan). Sebagian kosong = ditolak.
     *
     * @return array{galat: ?string, nilai: ?float}
     */
    public static function simpanNilai(TugasPengumpulan $p, array $input): array
    {
        $tugas = $p->tugas;
        $kriterias = $tugas->kriterias()->get();

        $nilai = [];
        $kosong = 0;
        foreach ($kriterias as $k) {
            $mentah = $input[$k->id] ?? '';
            $mentah = is_string($mentah) ? trim($mentah) : $mentah;

            if ($mentah === '' || $mentah === null) {
                $kosong++;
                continue;
            }
            if (! is_numeric($mentah)) {
                return ['galat' => 'Nilai "'.$k->nama.'" harus berupa angka.', 'nilai' => null];
            }
            $v = (float) $mentah;

            if ($k->tipe === 'ceklis') {
                if (! in_array($v, [0.0, 1.0], true)) {
                    return ['galat' => 'Kriteria "'.$k->nama.'" hanya boleh Ya atau Tidak.', 'nilai' => null];
                }
            } elseif ($k->tipe === 'poin') {
                if ($v != floor($v) || $v < 1 || $v > $k->maks) {
                    return ['galat' => 'Poin "'.$k->nama.'" harus bilangan bulat 1 sampai '.$k->maks.'.', 'nilai' => null];
                }
            } elseif ($v < 0 || $v > $k->maks) {
                return ['galat' => 'Nilai "'.$k->nama.'" harus antara 0 dan '.$k->maks.'.', 'nilai' => null];
            }
            $nilai[$k->id] = round($v, 2);
        }

        if ($kosong > 0 && $kosong < $kriterias->count()) {
            return ['galat' => 'Isi semua kriteria ('.$kosong.' belum terisi) atau kosongkan semuanya untuk hanya memverifikasi.', 'nilai' => null];
        }

        $total = $nilai ? HitungNilai::total($kriterias, $nilai) : null;

        DB::transaction(function () use ($p, $nilai, $total) {
            TugasNilaiKriteria::where('pengumpulan_id', $p->id)->delete();
            foreach ($nilai as $kid => $v) {
                TugasNilaiKriteria::create(['pengumpulan_id' => $p->id, 'tugas_kriteria_id' => $kid, 'nilai' => $v]);
            }
            $p->update(['nilai' => $total, 'dinilai_pada' => $total === null ? null : now()]);
        });

        return ['galat' => null, 'nilai' => $total];
    }

    /**
     * Samakan kriteria Penilaian dengan rubrik tugas supaya halaman Penilaian punya satu kolom per kriteria.
     * Mengembalikan [tugas_kriteria_id => kriteria penilaian], atau null kalau tugas tidak memakai rubrik
     * (dalam hal itu sisa kriteria rubrik lama dibersihkan, lalu pemanggil memakai satu kolom "Nilai Tugas").
     *
     * @return array<int, \App\Models\PenilaianKriteria>|null
     */
    public static function sinkronKriteria(Penilaian $pen, Tugas $tugas): ?array
    {
        $tk  = $tugas->kriterias()->get();
        $ada = $pen->kriterias()->orderBy('urutan')->orderBy('id')->get();

        if ($tk->isEmpty()) {
            if ($ada->count() > 1) {
                $pen->kriterias()->delete();   // sisa rubrik yang sudah dilepas
            }

            return null;
        }

        $sama = $ada->count() === $tk->count() && $tk->values()->every(
            fn ($k, $i) => $ada[$i]->nama === $k->nama && $ada[$i]->tipe === $k->tipe
                && (int) $ada[$i]->maks === (int) $k->maks && (int) $ada[$i]->bobot === (int) $k->bobot
        );

        if (! $sama) {
            $pen->kriterias()->delete();
            $ada = collect();
            foreach ($tk->values() as $i => $k) {
                $ada->push($pen->kriterias()->create([
                    'nama' => $k->nama, 'tipe' => $k->tipe, 'maks' => $k->maks, 'bobot' => $k->bobot, 'urutan' => $i,
                ]));
            }
        }

        $peta = [];
        foreach ($tk->values() as $i => $k) {
            $peta[$k->id] = $ada[$i];
        }

        return $peta;
    }

    /**
     * Rincian untuk ditampilkan: tiap kriteria dengan nilainya dan porsi pengaruhnya.
     * @return array<int, array{nama:string, tipe:string, maks:int, bobot:int, porsi:int, nilai:?float}>
     */
    public static function rincian(Tugas $tugas, ?TugasPengumpulan $p): array
    {
        $kriterias = $tugas->kriterias()->get();
        $jumlah = max(1, (int) $kriterias->sum('bobot'));
        $nilai = $p ? TugasNilaiKriteria::where('pengumpulan_id', $p->id)->pluck('nilai', 'tugas_kriteria_id') : collect();

        return $kriterias->map(fn ($k) => [
            'id' => $k->id, 'nama' => $k->nama, 'tipe' => $k->tipe, 'maks' => (int) $k->maks, 'bobot' => (int) $k->bobot,
            'porsi' => (int) round($k->bobot / $jumlah * 100),
            'nilai' => $nilai->has($k->id) ? (float) $nilai->get($k->id) : null,
        ])->all();
    }

    /** Teks nilai satu kriteria untuk tampilan. */
    public static function teks(array $r): string
    {
        if ($r['nilai'] === null) {
            return '-';
        }
        $angka = rtrim(rtrim(number_format($r['nilai'], 2, ',', ''), '0'), ',');

        return match ($r['tipe']) {
            'ceklis' => $r['nilai'] > 0 ? 'Ya' : 'Tidak',
            default  => $angka.' dari '.$r['maks'],
        };
    }
}