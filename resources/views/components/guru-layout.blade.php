@props(['judul' => 'Beranda'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }} - Asisten Guru</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        $menu = [
            ['Beranda', 'kelas.index', 'kelas.*'],
        ];
    @endphp

    <div class="md:flex min-h-screen">
        {{-- Sidebar: tablet & desktop --}}
        <aside class="hidden md:flex md:flex-col md:w-60 shrink-0 nb-side p-4 gap-2">
            <div class="judul text-2xl mb-4">Asisten<br>Guru</div>
            @foreach ($menu as [$nama, $rute, $pola])
                <a href="{{ route($rute) }}" class="nb-link {{ request()->routeIs($pola) ? 'aktif' : '' }}">{{ $nama }}</a>
            @endforeach
            <form method="POST" action="{{ route('logout') }}" class="mt-auto">
                @csrf
                <button class="nb-btn putih w-full">Keluar</button>
            </form>
        </aside>

        {{-- Konten --}}
        <main class="flex-1 p-4 md:p-8 pb-28 md:pb-8 max-w-6xl w-full">
            {{ $slot }}
        </main>
    </div>

    {{-- Menu bawah: HP --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 bg-white nb-bottom flex justify-around p-2 gap-2">
        @foreach ($menu as [$nama, $rute, $pola])
            <a href="{{ route($rute) }}" class="nb-link flex-1 justify-center {{ request()->routeIs($pola) ? 'aktif' : '' }}">{{ $nama }}</a>
        @endforeach
        <form method="POST" action="{{ route('logout') }}" class="flex-1">
            @csrf
            <button class="nb-link w-full justify-center">Keluar</button>
        </form>
    </nav>
</body>
</html>