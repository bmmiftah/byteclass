@props(['judul' => 'Beranda'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $judul }} - Asisten Guru</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        // [nama, route, pola route yang dianggap "sedang di sini", ikon]
        $menu = [
            ['Beranda', 'dashboard',     ['dashboard'], 'beranda'],
            ['Kelas',   'kelas.index',   ['kelas.*', 'siswa.*', 'denah.*', 'kelompok.*', 'penilaian.*', 'materi.*', 'presensi.*', 'alat.*', 'catatan.*', 'akun.*', 'ujian.*', 'tugas.*', 'leger.*', 'pengumuman.*', 'agenda.*', 'poin.*'], 'kelas'],
            ['Pustaka', 'pustaka.index', ['pustaka.*', 'rubrik.*'], 'pustaka'],
            ['Mapel',   'mapel.index',   ['mapel.*'], 'mapel'],
            ['Soal',    'soal.index',    ['soal.*'], 'soal'],
            ['Jurnal',  'jurnal.index',  ['jurnal.*'], 'jurnal'],
        ];

        if (auth()->user()->adalahAdmin()) {
            $menu[] = ['Admin', 'admin.guru.index', ['admin.*', 'backup.*'], 'admin'];
        }

        // HP: 4 menu yang paling sering dipakai jadi tab; sisanya masuk "Lainnya"
        $namaTab = ['Beranda', 'Kelas', 'Soal', 'Jurnal'];
        $tab     = array_values(array_filter($menu, fn ($m) => in_array($m[0], $namaTab, true)));
        $lain    = array_values(array_filter($menu, fn ($m) => ! in_array($m[0], $namaTab, true)));
        $lainAktif = collect($lain)->contains(fn ($m) => request()->routeIs(...$m[2]));
    @endphp

    <div class="md:flex min-h-screen">
        {{-- Sidebar: tablet & desktop --}}
        <aside class="hidden md:flex md:flex-col md:w-60 shrink-0 nb-side p-4 gap-2 print:hidden">
            <div class="judul text-2xl mb-4">Asisten<br>Guru</div>
            @foreach ($menu as [$nama, $rute, $pola, $ikon])
                @php $aktif = request()->routeIs(...$pola); @endphp
                <a href="{{ route($rute) }}" class="nb-link {{ $aktif ? 'aktif' : '' }}" @if ($aktif) aria-current="page" @endif>
                    <x-ikon :nama="$ikon" />
                    <span>{{ $nama }}</span>
                </a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}" class="mt-auto">
                @csrf
                <button class="nb-btn putih blok"><x-ikon nama="keluar" /> Keluar</button>
            </form>
        </aside>

        {{-- Konten --}}
        <main class="flex-1 min-w-0 p-4 md:p-8 pb-28 md:pb-8 max-w-6xl w-full print:max-w-none print:p-0">
            {{ $slot }}
        </main>
    </div>

    {{-- Menu bawah: HP --}}
    <div x-data="{ lainnya: false }" x-effect="document.body.classList.toggle('kunci-gulir', lainnya)" @keydown.escape.window="lainnya = false" class="md:hidden print:hidden">
        <nav class="fixed bottom-0 inset-x-0 nb-bottom grid grid-cols-5 gap-1" aria-label="Menu utama">
            @foreach ($tab as [$nama, $rute, $pola, $ikon])
                @php $aktif = request()->routeIs(...$pola); @endphp
                <a href="{{ route($rute) }}" class="nb-tab {{ $aktif ? 'aktif' : '' }}" @if ($aktif) aria-current="page" @endif>
                    <x-ikon :nama="$ikon" />
                    <span>{{ $nama }}</span>
                </a>
            @endforeach
            <button type="button" class="nb-tab" :class="(lainnya || {{ $lainAktif ? 'true' : 'false' }}) ? 'aktif' : ''" @click="lainnya = !lainnya" aria-haspopup="dialog" :aria-expanded="lainnya.toString()">
                <x-ikon nama="lainnya" />
                <span>Lainnya</span>
            </button>
        </nav>

        <div x-show="lainnya" x-cloak>
            <div class="nb-sheet-latar" @click="lainnya = false"></div>
            <div class="nb-sheet" role="dialog" aria-modal="true" aria-label="Menu lainnya">
                <div class="flex items-center justify-between mb-3">
                    <span class="judul text-xl">Menu lainnya</span>
                    <button type="button" class="nb-btn putih kecil" @click="lainnya = false" aria-label="Tutup menu"><x-ikon nama="tutup" /></button>
                </div>
                <div class="grid gap-3">
                    @foreach ($lain as [$nama, $rute, $pola, $ikon])
                        @php $aktif = request()->routeIs(...$pola); @endphp
                        <a href="{{ route($rute) }}" class="nb-link kotak {{ $aktif ? 'aktif' : '' }}" @if ($aktif) aria-current="page" @endif>
                            <x-ikon :nama="$ikon" />
                            <span>{{ $nama }}</span>
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="nb-btn merah blok"><x-ikon nama="keluar" /> Keluar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
