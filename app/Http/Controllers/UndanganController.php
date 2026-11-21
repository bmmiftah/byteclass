<?php

namespace App\Http\Controllers;

use App\Models\Undangan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class UndanganController extends Controller
{
    public function form(string $token)
    {
        $undangan = Undangan::where('token', $token)->first();

        if (! $undangan || ! $undangan->masihBerlaku()) {
            return response()->view('undangan.tidak-valid', [], 410);
        }

        return view('undangan.form', compact('undangan'));
    }

    public function daftar(Request $request, string $token)
    {
        $user = DB::transaction(function () use ($request, $token) {
            $undangan = Undangan::where('token', $token)->lockForUpdate()->first();
            abort_unless($undangan && $undangan->masihBerlaku(), 410);

            $data = $request->validate([
                'name' => 'required|string|max:100',
                'email' => ['required', 'email', 'max:150', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ]);

            // Undangan yang terikat email hanya boleh dipakai email itu
            if ($undangan->email && strcasecmp($undangan->email, $data['email']) !== 0) {
                abort(422, 'Undangan ini khusus untuk email ' . $undangan->email . '.');
            }

            $user = new User();
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'peran' => $undangan->peran,
                'aktif' => true,
            ])->save();

            $undangan->forceFill([
                'dipakai_pada' => now(),
                'dipakai_oleh' => $user->id,
            ])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}