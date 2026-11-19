<x-siswa-layout :judul="$kelas->nama" :kelas="$kelas">
    <h1 class="text-3xl md:text-4xl mb-1">{{ $kelas->nama }}</h1>
    <p class="mb-6">Pilih bab untuk mulai belajar.</p>

    @forelse ($babs as $namaMapel => $daftar)
        <h2 class="text-2xl mt-8 mb-3">{{ $namaMapel }}</h2>

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
        <div class="nb-card">
            <p class="font-bold">Belum ada materi yang terbit.</p>
            <p>Coba lagi nanti, atau tanyakan ke gurumu.</p>
        </div>
    @endforelse
</x-siswa-layout>