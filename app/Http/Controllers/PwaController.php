<?php

namespace App\Http\Controllers;

class PwaController extends Controller
{
    public function manifest()
    {
        $data = [
            'id'               => '/saya',
            'name'             => 'Ayo Belajar - Dashboard Saya',
            'short_name'       => 'Ayo Belajar',
            'description'      => 'Tugas, ujian, pengumuman, dan kalender kelas dalam satu aplikasi.',
            'lang'             => 'id',
            'dir'              => 'ltr',
            'start_url'        => '/saya',
            'scope'            => '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#FFF8E7',
            'theme_color'      => '#FF8C42',
            'categories'       => ['education'],
            'icons'            => [
                ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts'        => [
                ['name' => 'Tugas', 'url' => '/saya/tugas', 'icons' => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png']]],
                ['name' => 'Kalender', 'url' => '/saya/kalender', 'icons' => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png']]],
            ],
        ];

        return response(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            200,
            [
                'Content-Type'  => 'application/manifest+json; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    /**
     * Service worker dilayani dari /saya/sw.js tapi didaftarkan dengan cakupan "/" (diizinkan header
     * Service-Worker-Allowed). Cakupan "/saya/" tidak mencakup alamat "/saya" (tanpa garis miring), yaitu beranda.
     * Yang benar-benar ditangani sw.js tetap hanya alamat di bawah /saya.
     */
    public function sw()
    {
        $manifestVite = public_path('build/manifest.json');
        $versi = is_file($manifestVite) ? substr(md5_file($manifestVite), 0, 10) : 'dev';

        return response()->view('pwa.sw', ['versi' => $versi])->withHeaders([
            'Content-Type'           => 'text/javascript; charset=utf-8',
            'Cache-Control'          => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline()
    {
        return view('pwa.offline');
    }
}