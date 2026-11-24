<?php

namespace App\Http\Controllers;

use App\Models\AkunSiswa;
use App\Services\KalenderIcs;
use Illuminate\Http\Request;

/** Langganan kalender (.ics): tautan rahasia per siswa yang bisa ditempel ke Google Calendar atau kalender HP. */
class KalenderIcsController extends Controller
{
    /** Diakses aplikasi kalender, tanpa login: yang menjaga adalah token rahasia di alamatnya. */
    public function feed(string $token)
    {
        $akun = AkunSiswa::with('siswa.kelas')->where('kal_token', $token)->first();
        abort_unless($akun && $akun->siswa && $akun->siswa->aktif && $akun->siswa->kelas, 404);

        return response(KalenderIcs::untuk($akun), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="kalender-kelas.ics"',
            'Cache-Control'       => 'private, max-age=300',
            'X-Robots-Tag'        => 'noindex, nofollow',
        ]);
    }

    public function aktifkan(Request $request)
    {
        $akun = $request->attributes->get('akun');
        $akun->kal_token ?: $akun->buatTokenKalender();

        return redirect()->to(route('saya.kalender') . '#langganan')
            ->with('ok', 'Langganan kalender aktif. Salin tautannya di bawah.');
    }

    public function ulang(Request $request)
    {
        $request->attributes->get('akun')->buatTokenKalender();

        return redirect()->to(route('saya.kalender') . '#langganan')
            ->with('ok', 'Tautan baru dibuat. Tautan lama sudah mati; tempel tautan baru ini di aplikasi kalender lu.');
    }

    public function matikan(Request $request)
    {
        $request->attributes->get('akun')->forceFill(['kal_token' => null])->save();

        return redirect()->to(route('saya.kalender') . '#langganan')
            ->with('ok', 'Langganan kalender dimatikan. Tautan lama tidak berlaku lagi.');
    }
}