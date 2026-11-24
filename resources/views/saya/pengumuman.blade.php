<x-siswa-layout judul="Pengumuman" :kelas="$kelas">
    <h1 class="text-3xl mb-4">Pengumuman</h1>

    <x-saya-nav />

    <div class="space-y-4">
        @forelse ($daftar as $p)
            <article class="nb-card" style="background: {{ $p->disematkan ? 'var(--kuning)' : '#fff' }}">
                <h2 class="text-xl">
                    @if ($p->disematkan)
                        <span aria-label="Disematkan">&#9733;</span>
                    @endif
                    {{ $p->judul }}
                    @if ($p->baru)
                        <span class="nb-chip" style="background: var(--pink)">Baru</span>
                    @endif
                </h2>
                <p class="text-sm mb-2">{{ $p->waktu->locale('id')->translatedFormat('l, j F Y') }}</p>
                <div class="whitespace-pre-line">{{ $p->isi }}</div>
            </article>
        @empty
            <div class="nb-card">
                <p class="font-bold">Belum ada pengumuman.</p>
            </div>
        @endforelse
    </div>
</x-siswa-layout>