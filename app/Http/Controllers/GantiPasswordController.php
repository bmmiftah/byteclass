<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class GantiPasswordController extends Controller
{
    public function form(Request $request)
    {
        // Hanya untuk akun yang memang diminta mengganti password
        if (! $request->user()->harus_ganti_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.ganti-password');
    }

    public function simpan(Request $request)
    {
        abort_unless($request->user()->harus_ganti_password, 403);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->password = $data['password'];
        $user->harus_ganti_password = false;
        $user->save();

        return redirect()->route('dashboard')->with('ok', 'Password diganti.');
    }
}