<?php

namespace App\Http\Controllers;

use App\Models\Undangan;
use App\Models\User;
use App\Services\PenyimpananTugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminGuruController extends Controller
{
    public function index()
    {
        $gurus = User::withCount('kelas')
            ->orderByRaw("peran = 'admin' desc")
            ->orderBy('name')
            ->get();

        // Jumlah siswa per guru
        $siswaPerGuru = DB::table('siswas')
            ->join('kelas', 'kelas.id', '=', 'siswas.kelas_id')
            ->whereNull('siswas.deleted_at')
            ->whereNull('kelas.deleted_at')
            ->groupBy('kelas.guru_id')
            ->selectRaw('kelas.guru_id as guru_id, count(*) as jumlah')
            ->pluck('jumlah', 'guru_id');

        // Perkiraan pemakaian penyimpanan file materi per guru (byte)
        $pakaiPerGuru = DB::table('materi_items')
            ->join('materi_babs', 'materi_babs.id', '=', 'materi_items.bab_id')
            ->join('kelas', 'kelas.id', '=', 'materi_babs.kelas_id')
            ->groupBy('kelas.guru_id')
            ->selectRaw('kelas.guru_id as guru_id, coalesce(sum(materi_items.ukuran), 0) as byte')
            ->pluck('byte', 'guru_id');

        // File tugas siswa per guru (byte), dijumlahkan dengan materi di tampilan
        $pakaiTugasPerGuru = DB::table('tugas_berkas')
            ->join('tugas_pengumpulans', 'tugas_pengumpulans.id', '=', 'tugas_berkas.pengumpulan_id')
            ->join('tugas', 'tugas.id', '=', 'tugas_pengumpulans.tugas_id')
            ->join('kelas', 'kelas.id', '=', 'tugas.kelas_id')
            ->whereNotNull('tugas_berkas.path')
            ->groupBy('kelas.guru_id')
            ->selectRaw('kelas.guru_id as guru_id, coalesce(sum(tugas_berkas.ukuran), 0) as byte')
            ->pluck('byte', 'guru_id');

        $totalByte = PenyimpananTugas::totalByte();
        $kuotaByte = PenyimpananTugas::kuotaByte();

        $undangans = Undangan::whereNull('dipakai_pada')
            ->where('berlaku_sampai', '>', now())
            ->latest()
            ->get();

        return view('admin.guru.index', compact(
            'gurus',
            'siswaPerGuru',
            'pakaiPerGuru',
            'pakaiTugasPerGuru',
            'totalByte',
            'kuotaByte',
            'undangans'
        ));
    }

    public function undang(Request $request)
    {
        $data = $request->validate([
            'email' => 'nullable|email|max:150|unique:users,email',
            'hari'  => 'required|integer|min:1|max:30',
            'peran' => 'required|in:guru,admin',
        ], [
            'email.unique' => 'Email itu sudah terdaftar sebagai guru.',
        ]);

        $undangan = Undangan::create([
            'email'          => $data['email'] ?? null,
            'peran'          => $data['peran'],
            'dibuat_oleh'    => $request->user()->id,
            'berlaku_sampai' => now()->addDays((int) $data['hari']),
        ]);

        return back()->with('link_undangan', route('undangan.form', $undangan->token));
    }

    public function batalUndangan(Undangan $undangan)
    {
        $undangan->delete();

        return back()->with('ok', 'Undangan dibatalkan.');
    }

    public function ubahStatus(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Tidak bisa menonaktifkan akun sendiri.');

        $user->aktif = ! $user->aktif;
        $user->save();

        return back()->with(
            'ok',
            $user->name . ($user->aktif ? ' diaktifkan kembali.' : ' dinonaktifkan. Datanya tetap tersimpan.')
        );
    }

    public function ubahPeran(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Tidak bisa mengubah peran akun sendiri.');

        $user->peran = $user->adalahAdmin() ? 'guru' : 'admin';
        $user->save();

        return back()->with('ok', $user->name . ' sekarang berperan sebagai ' . $user->peran . '.');
    }

    public function resetPassword(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Ubah password sendiri lewat halaman Profil.');

        $sementara = Str::password(10, true, true, false, false);
        $user->password = $sementara; // Otomatis di-hash oleh model User
        $user->harus_ganti_password = true;
        $user->save();

        return back()->with('sandi_sementara', [
            'nama'  => $user->name,
            'email' => $user->email,
            'sandi' => $sementara,
        ]);
    }
}