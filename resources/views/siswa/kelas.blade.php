<x-siswa-layout :judul="$kelas->nama" :kelas="$kelas">
    {{-- Kepala dashboard --}}
    <div class="nb-card mb-6 muncul" style="background: {{ $kelas->warna_hex }}">
        <p class="text-sm font-bold">Dashboard kelas</p>
        <h1 class="text-3xl md:text-4xl">{{ $kelas->nama }}</h1>
        <p class="mt-1">{{ $kelas->tahun_ajaran }} &middot; Semester {{ $kelas->semester }}</p>
        <div class="mt-4">
            @if (session('akun_id'))
                <a class="nb-btn putih" href="{{ route('saya.beranda') }}">Dashboard Saya</a>
            @else
                <a class="nb-btn putih" href="{{ route('saya.masuk') }}">Masuk ke Dashboard Saya</a>
            @endif
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <a
            href="#materi"
            class="nb-card hover muncul block"
            style="background: var(--biru); animation-delay: 60ms"
        >
            <p class="judul text-4xl">{{ $jumlahBab }}</p>
            <p class="font-bold">Bab</p>
        </a>

        <a
            href="#materi"
            class="nb-card hover muncul block"
            style="background: var(--pink); animation-delay: 120ms"
        >
            <p class="judul text-4xl">{{ $jumlahMateri }}</p>
            <p class="font-bold">Materi</p>
        </a>

        @if (Route::has('cbt.masuk'))
            <a
                href="{{ route('cbt.masuk') }}"
                class="nb-card hover muncul block"
                style="background: var(--hijau); animation-delay: 180ms"
            >
                <p class="judul text-4xl">&rarr;</p>
                <p class="font-bold">Ujian (CBT)</p>
            </a>
        @endif
    </div>

    {{-- Baru ditambahkan --}}
    @if ($terbaru->isNotEmpty())
        <h2 class="text-2xl mb-3">Baru ditambahkan</h2>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-10">
            @foreach ($terbaru as $b)
                <a
                    href="{{ route('siswa.bab', [$kelas->kode_publik, $b]) }}"
                    class="nb-card hover muncul block"
                    style="animation-delay: {{ $loop->index * 60 }}ms"
                >
                    @if ($b->baru())
                        <span class="stiker mb-2">Baru</span>
                    @endif

                    <p class="text-sm">{{ $b->mapel?->nama ?? 'Umum' }}</p>
                    <h3 class="text-xl">{{ $b->judul }}</h3>
                    <p class="mt-2 font-bold text-sm">{{ $b->items_count }} materi</p>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Semua materi per mapel --}}
    <h2 id="materi" class="text-2xl mb-1">Materi per mata pelajaran</h2>

    @forelse ($babs as $namaMapel => $daftar)
        <h3 class="text-xl mt-6 mb-3">{{ $namaMapel }}</h3>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($daftar as $b)
                @php
                    $warna = ['var(--kuning)', 'var(--biru)', 'var(--pink)', 'var(--hijau)', 'var(--oranye)'][$loop->index % 5];
                @endphp

                <a
                    href="{{ route('siswa.bab', [$kelas->kode_publik, $b]) }}"
                    class="nb-card hover muncul block"
                    style="background: {{ $warna }}; animation-delay: {{ $loop->index * 60 }}ms"
                >
                    @if ($b->baru())
                        <span class="stiker mb-2">Baru</span>
                    @endif

                    <h3 class="text-xl">{{ $b->judul }}</h3>

                    @if ($b->deskripsi)
                        <p class="text-sm mt-1">{{ \Illuminate\Support\Str::limit($b->deskripsi, 90) }}</p>
                    @endif

                    <p class="mt-3 font-bold text-sm">{{ $b->items_count }} materi</p>
                </a>
            @endforeach
        </div>
    @empty
        <div class="nb-card mt-4">
            <p class="font-bold">Belum ada materi yang terbit.</p>
            <p>Coba lagi nanti, atau tanyakan ke gurumu.</p>
        </div>
    @endforelse
</x-siswa-layout>