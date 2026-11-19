<x-guru-layout judul="Rubrik">
    <h1 class="text-3xl mb-2">Rubrik Penilaian</h1>
    <p class="mb-6">Rubrik adalah daftar kriteria penilaian yang bisa dipakai ulang di banyak penilaian.</p>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('rubrik.store') }}" class="nb-card mb-8 flex flex-wrap items-end gap-4">
        @csrf

        <div class="flex-1 min-w-[220px]">
            <label class="nb-label" for="nama">Nama rubrik</label>
            <input class="nb-input" id="nama" name="nama" placeholder="mis. Praktik IPA, Presentasi" required>
            @error('nama')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <button class="nb-btn">+ Buat Rubrik</button>
    </form>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($rubriks as $r)
            <div class="nb-card muncul" style="animation-delay: {{ $loop->index * 60 }}ms">
                <h2 class="text-xl">{{ $r->nama }}</h2>
                <p>{{ $r->kriterias_count }} kriteria</p>

                <div class="flex flex-wrap gap-2 mt-4">
                    <a class="nb-btn biru" href="{{ route('rubrik.edit', $r) }}">Edit</a>

                    <form method="POST" action="{{ route('rubrik.salin', $r) }}">
                        @csrf
                        <button class="nb-btn putih">Salin</button>
                    </form>
                </div>

                <form
                    method="POST"
                    action="{{ route('rubrik.destroy', $r) }}"
                    class="mt-3"
                    onsubmit="return confirm('Hapus rubrik ini? Penilaian lama tidak terpengaruh.')"
                >
                    @csrf
                    @method('DELETE')
                    <button class="font-bold underline">Hapus</button>
                </form>
            </div>
        @empty
            <div class="nb-card sm:col-span-2 lg:col-span-3">
                <p class="font-bold">Belum ada rubrik.</p>
                <p>Buat rubrik pertama di atas, lalu atur kriterianya.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>