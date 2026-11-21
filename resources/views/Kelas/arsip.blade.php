<x-guru-layout judul="Arsip Kelas">
    <a href="{{ route('kelas.index') }}" class="font-bold underline">&larr; Kelas</a>
    <h1 class="text-3xl mt-3 mb-6">Arsip Kelas</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">
            {{ session('ok') }}
        </div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($kelas as $k)
            <div class="nb-card">
                <h2 class="text-xl">{{ $k->nama }}</h2>
                <p>{{ $k->tahun_ajaran }} &middot; Semester {{ $k->semester }} &middot; {{ $k->siswas_count }} siswa</p>

                <div class="flex flex-wrap gap-3 mt-4 items-center">
                    <a class="nb-btn putih" href="{{ route('kelas.show', $k) }}">Lihat</a>
                    <form method="POST" action="{{ route('kelas.arsipkan', $k) }}">
                        @csrf
                        <button class="nb-btn biru">Pulihkan</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="nb-card sm:col-span-2 lg:col-span-3">
                <p class="font-bold">Belum ada kelas yang diarsipkan.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>