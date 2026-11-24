<?php

namespace App\Http\Controllers;

use App\Models\LanggananPush;
use App\Services\PengirimWebPush;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Pendaftaran notifikasi push dari HP siswa (dipanggil lewat JavaScript, balasan JSON). */
class SayaPushController extends Controller
{
    public const MAKS_PERANGKAT = 5;

    public function simpan(Request $request)
    {
        $d = $request->validate([
            'endpoint'    => ['required', 'url:https', 'max:600'],
            'keys.p256dh' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-=]+$/'],
            'keys.auth'   => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-=]+$/'],
            'agen'        => ['nullable', 'string', 'max:160'],
        ]);

        if (! self::hostDiizinkan($d['endpoint'])) {
            throw ValidationException::withMessages(['endpoint' => 'Layanan push dari browser ini belum didukung.']);
        }

        $akun = $request->attributes->get('akun');

        // Endpoint yang sama dari akun lain (HP dipakai bergantian) dipindah ke akun yang sedang masuk
        LanggananPush::updateOrCreate(
            ['endpoint_hash' => LanggananPush::hash($d['endpoint'])],
            [
                'akun_siswa_id' => $akun->id,
                'endpoint'      => $d['endpoint'],
                'p256dh'        => $d['keys']['p256dh'],
                'auth'          => $d['keys']['auth'],
                'agen'          => isset($d['agen']) ? mb_substr($d['agen'], 0, 160) : null,
            ],
        );

        // Simpan paling banyak beberapa perangkat per siswa; yang terlama dibuang
        $ids = LanggananPush::where('akun_siswa_id', $akun->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->pluck('id');

        if ($ids->count() > self::MAKS_PERANGKAT) {
            LanggananPush::whereIn('id', $ids->slice(self::MAKS_PERANGKAT)->all())->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function hapus(Request $request)
    {
        $d = $request->validate(['endpoint' => ['required', 'string', 'max:600']]);

        LanggananPush::where('akun_siswa_id', $request->attributes->get('akun')->id)
            ->where('endpoint_hash', LanggananPush::hash($d['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Kirim satu notifikasi percobaan ke semua perangkat siswa ini. */
    public function uji(Request $request, PengirimWebPush $pengirim)
    {
        if (! $pengirim->siap()) {
            return response()->json(['pesan' => 'Notifikasi belum disiapkan di server (kunci VAPID kosong).'], 503);
        }

        $akun = $request->attributes->get('akun');
        $ok = 0;

        foreach (LanggananPush::where('akun_siswa_id', $akun->id)->get() as $l) {
            $hasil = $pengirim->kirim($l, [
                'judul' => 'Notifikasi aktif',
                'isi'   => 'Mantap! Pengingat tugas dan ujian akan muncul di sini.',
                'url'   => route('saya.beranda', [], false),
                'tag'   => 'uji',
            ], 300);

            if ($hasil === 'kadaluarsa') {
                $l->delete();
            } elseif ($hasil === 'ok') {
                $ok++;
            }
        }

        return response()->json(['terkirim' => $ok]);
    }

    /** Hanya layanan push milik browser besar (daftar di config/pengingat.php), lewat https biasa. */
    public static function hostDiizinkan(string $endpoint): bool
    {
        $bagian = parse_url($endpoint);
        $host = strtolower($bagian['host'] ?? '');

        if (($bagian['scheme'] ?? '') !== 'https'
            || isset($bagian['user'])
            || isset($bagian['pass'])
            || ($host === '')
            || (isset($bagian['port']) && (int) $bagian['port'] !== 443)
        ) {
            return false;
        }

        foreach (config('pengingat.host_push') as $izin) {
            if ($host === $izin || str_ends_with($host, '.' . $izin)) {
                return true;
            }
        }

        return false;
    }
}