<x-siswa-layout :judul="'Cari ' . $q" :kelas="$kelas">
    <a href="{{ route('siswa.kelas', $kelas->kode_publik) }}" class="font-bold underline">&larr; Semua bab</a>
    <h1 class="text-3xl mt-3 mb-6">Hasil pencarian: "{{ $q }}"</h1>

    @if (mb_strlen($q) < 2)
        <div class="nb-card">
            <p>Ketik minimal 2 huruf di kotak pencarian.</p>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($hasil as $b)
                <a
                    href="{{ route('siswa.bab', [$kelas->kode_publik, $b]) }}"
                    class="nb-card hover muncul block"
                    style="animation-delay: {{ $loop->index * 60 }}ms"
                >
                    <p class="text-sm">{{ $b->mapel?->nama ?? 'Umum' }}</p>
                    <h2 class="text-xl">{{ $b->judul }}</h2>
                </a>
            @empty
                <div class="nb-card sm:col-span-2 lg:col-span-3">
                    <p class="font-bold">Tidak ada yang cocok.</p>
                    <p>Coba kata lain yang lebih singkat.</p>
                </div>
            @endforelse
        </div>
    @endif
</x-siswa-layout>