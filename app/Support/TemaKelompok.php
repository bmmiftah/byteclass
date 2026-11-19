<?php

namespace App\Support;

class TemaKelompok
{
    public static function daftar(): array
    {
        return [
            'angka'  => null, // "Kelompok 1, Kelompok 2, ..."
            'warna'  => ['Merah', 'Biru', 'Hijau', 'Kuning', 'Oranye', 'Ungu', 'Pink', 'Hitam', 'Putih', 'Cokelat', 'Abu-abu', 'Tosca'],
            'hewan'  => ['Elang', 'Harimau', 'Singa', 'Gajah', 'Lumba-lumba', 'Kuda', 'Serigala', 'Beruang', 'Komodo', 'Banteng', 'Cendrawasih', 'Rajawali'],
            'planet' => ['Merkurius', 'Venus', 'Bumi', 'Mars', 'Jupiter', 'Saturnus', 'Uranus', 'Neptunus'],
            'buah'   => ['Apel', 'Jeruk', 'Mangga', 'Anggur', 'Semangka', 'Nanas', 'Pisang', 'Melon', 'Stroberi', 'Durian', 'Salak', 'Manggis'],
        ];
    }

    public static function nama(string $tema, int $indeks): string
    {
        $daftar = self::daftar()[$tema] ?? null;

        return $daftar[$indeks] ?? 'Kelompok ' . ($indeks + 1);
    }
}