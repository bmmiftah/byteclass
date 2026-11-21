<?php

namespace App\Http\Middleware;

use App\Support\SiswaSesi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiswaMasuk
{
    public function handle(Request $request, Closure $next): Response
    {
        $akun = SiswaSesi::saatIni($request);

        if (! $akun) {
            if ($request->expectsJson()) {
                return response()->json(['pesan' => 'Sesi habis, masuk lagi.'], 401);
            }

            return redirect()->route($request->routeIs('saya.*') ? 'saya.masuk' : 'cbt.masuk');
        }

        $request->attributes->set('akun', $akun);

        return $next($request);
    }
}