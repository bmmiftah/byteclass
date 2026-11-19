<?php

namespace App\Http\Controllers;

use App\Models\Mapel;
use Illuminate\Http\Request;

class MapelController extends Controller
{
    public function index()
    {
        $mapels = auth()->user()->mapels()->orderBy('nama')->get();

        return view('mapel.index', compact('mapels'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:80',
        ]);

        auth()->user()->mapels()->create($data);

        return back()->with('ok', 'Mata pelajaran ditambahkan.');
    }

    public function destroy(Mapel $mapel)
    {
        abort_unless($mapel->guru_id === auth()->id(), 403);

        $mapel->delete();

        return back()->with('ok', 'Mata pelajaran dihapus.');
    }
}