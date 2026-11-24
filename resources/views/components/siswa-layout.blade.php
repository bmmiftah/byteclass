@props(['judul' => 'Materi Belajar', 'kelas' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judul }} - Ayo Belajar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap"
        rel="stylesheet"
    >
    <x-pwa-head />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="nb-head">
        <div class="max-w-5xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3 justify-between">
            <a
                href="{{ $kelas ? route('siswa.kelas', $kelas->kode_publik) : route('siswa.beranda') }}"
                class="judul text-xl"
            >Ayo Belajar</a>

            @if ($kelas)
                <form method="GET" action="{{ route('siswa.cari', $kelas->kode_publik) }}" class="flex gap-2">
                    <input
                        class="nb-input"
                        style="min-width: 0; width: 11rem"
                        type="search"
                        name="q"
                        placeholder="Cari materi..."
                        value="{{ request('q') }}"
                    >
                    <button class="nb-btn">Cari</button>
                </form>
            @endif
            @if (session('akun_id'))
                <a class="nb-btn putih" href="{{ route('saya.beranda') }}">Dashboard Saya</a>
            @elseif (! request()->routeIs('saya.masuk'))
                <a class="nb-btn putih" href="{{ route('saya.masuk') }}">Masuk</a>
            @endif
        </div>
    </header>

    <main class="max-w-5xl mx-auto p-4 md:p-8">
        {{ $slot }}
    </main>

    <footer class="max-w-5xl mx-auto px-4 pb-8 text-sm flex flex-wrap items-center gap-3">
        @if ($kelas)
            <span class="nb-chip">{{ $kelas->nama }}</span>
            <a class="underline" href="{{ route('siswa.beranda') }}">Ganti kelas</a>
        @endif
    </footer>
</body>
</html>