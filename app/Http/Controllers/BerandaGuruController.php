<?php

namespace App\Http\Controllers;

use App\Services\BerandaGuru;
use Illuminate\Http\Request;

class BerandaGuruController extends Controller
{
    public function index(Request $request)
    {
        $guru = $request->user();
        $d = BerandaGuru::data($guru);

        return view('beranda.guru', $d + [
            'guru'   => $guru,
            'sapaan' => BerandaGuru::sapaan($d['sekarang']),
        ]);
    }
}