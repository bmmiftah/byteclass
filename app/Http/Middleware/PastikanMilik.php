<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga isolasi data antar guru.
 * Setiap model di alamat (mis. /kelas/5, /denah/9) yang memakai trait MilikGuru
 * dicek: pemiliknya harus guru yang sedang login. Berlaku untuk SEMUA route di grup ini,
 * jadi satu controller yang lupa mengecek tetap tidak bocor.
 */
class PastikanMilik
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if ($user && $route) {
            foreach ($route->parameters() as $param) {
                if (! is_object($param) || ! method_exists($param, 'pemilikId')) {
                    continue;
                }

                $pemilik = $param->pemilikId();
                abort_if($pemilik === null, 404);
                abort_unless($pemilik === (int) $user->id, 403);
            }
        }

        return $next($request);
    }
}