<?php

namespace App\Support;

use App\Models\AkunSiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Satu pintu untuk semua urusan "siswa sedang masuk":
 * dipakai oleh login CBT (/cbt) dan login Dashboard Saya (/saya/masuk),
 * jadi siswa cukup masuk sekali untuk keduanya.
 */
class SiswaSesi
{
    public const COOKIE = 'siswa_ingat';

    public const HARI = 30;

    public static function masukkan(Request $request, AkunSiswa $akun, bool $ingat = false): void
    {
        $request->session()->regenerate();
        $request->session()->put('akun_id', $akun->id);
        $akun->forceFill(['terakhir_masuk' => now()])->save();

        if ($ingat) {
            $token = Str::random(40);
            $akun->forceFill(['ingat_token' => hash('sha256', $token)])->save();
            Cookie::queue(self::COOKIE, $akun->id . '|' . $token, self::HARI * 1440);

            return;
        }

        // Masuk tanpa "ingat": buang sisa ingatan lama supaya tidak ada akun orang lain yang "hidup lagi"
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public static function keluar(Request $request): void
    {
        if ($id = $request->session()->pull('akun_id')) {
            AkunSiswa::whereKey($id)->update(['ingat_token' => null]);
        }

        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /** Akun siswa yang sedang masuk (dari sesi, atau dipulihkan dari cookie "ingat"), atau null. */
    public static function saatIni(Request $request): ?AkunSiswa
    {
        $id = $request->session()->get('akun_id');
        $akun = $id ? AkunSiswa::with('siswa.kelas')->find($id) : null;
        $akun ??= self::pulihkan($request);

        if (! $akun || ! $akun->siswa || ! $akun->siswa->aktif) {
            $request->session()->forget('akun_id');

            return null;
        }

        return $akun;
    }

    public static function pulihkan(Request $request): ?AkunSiswa
    {
        $isi = (string) $request->cookie(self::COOKIE);

        if (! str_contains($isi, '|')) {
            return null;
        }

        [$id, $token] = explode('|', $isi, 2);
        $akun = ctype_digit($id) ? AkunSiswa::with('siswa.kelas')->find((int) $id) : null;

        if (! $akun || ! $akun->ingat_token || ! hash_equals($akun->ingat_token, hash('sha256', $token))) {
            return null;
        }

        $request->session()->regenerate();
        $request->session()->put('akun_id', $akun->id);

        return $akun;
    }
}