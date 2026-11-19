<x-guru-layout judul="Mata Pelajaran">
    <h1 class="text-3xl mb-6">Mata Pelajaran</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('mapel.store') }}" class="nb-card mb-8 flex flex-wrap items-end gap-4">
        @csrf

        <div class="flex-1 min-w-[220px]">
            <label class="nb-label" for="nama">Nama mata pelajaran</label>
            <input class="nb-input" id="nama" name="nama" placeholder="mis. IPA, Informatika" required>
            @error('nama')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <button class="nb-btn">+ Tambah</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($mapels as $m)
            <div class="nb-card flex items-center justify-between gap-3">
                <span class="font-bold text-lg">{{ $m->nama }}</span>

                <form method="POST" action="{{ route('mapel.destroy', $m) }}" onsubmit="return confirm('Hapus mapel ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="font-bold underline">Hapus</button>
                </form>
            </div>
        @empty
            <div class="nb-card sm:col-span-2 lg:col-span-3">
                <p>Belum ada mata pelajaran.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>