<x-siswa-layout :judul="$bab->judul" :kelas="$kelas">
    <a href="{{ route('siswa.kelas', $kelas->kode_publik) }}" class="font-bold underline">&larr; Semua bab</a>

    <div class="mt-3 mb-8">
        @if ($bab->mapel)
            <span class="nb-chip" style="background: var(--kuning)">{{ $bab->mapel->nama }}</span>
        @endif

        <h1 class="text-3xl md:text-4xl mt-2">{{ $bab->judul }}</h1>

        @if ($bab->deskripsi)
            <p class="mt-2">{{ $bab->deskripsi }}</p>
        @endif
    </div>

    <div class="space-y-8">
        @forelse ($bab->items as $item)
            <section class="muncul" style="animation-delay: {{ $loop->index * 60 }}ms">
                @include('siswa._item', ['item' => $item, 'kode' => $kelas->kode_publik])
            </section>
        @empty
            <div class="nb-card">
                <p>Belum ada materi di bab ini.</p>
            </div>
        @endforelse
    </div>
</x-siswa-layout>