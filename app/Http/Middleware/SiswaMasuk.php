<?php

namespace App\Http\Middleware;

use App\Models\AkunSiswa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiswaMasuk
{
    public function handle(Request $request, Closure $next): Response
    {
        $akunId = $request->session()->get('akun_id');
        $akun = $akunId ? AkunSiswa::with('siswa.kelas')->find($akunId) : null;

        if (! $akun || ! $akun->siswa || ! $akun->siswa->aktif) {
            $request->session()->forget('akun_id');

            if ($request->expectsJson()) {
                return response()->json(['pesan' => 'Sesi habis, masuk lagi.'], 401);
            }

            return redirect()->route('cbt.masuk');
        }

        $request->attributes->set('akun', $akun);

        return $next($request);
    }
}