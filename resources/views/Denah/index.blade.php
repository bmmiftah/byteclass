<x-guru-layout :judul="'Denah ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">Denah Tempat Duduk {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('denah.store', $kelas) }}" class="nb-card mb-8 grid gap-4 md:grid-cols-5 items-end">
        @csrf

        <div class="md:col-span-2">
            <label class="nb-label" for="nama">Nama denah</label>
            <input class="nb-input" id="nama" name="nama" value="{{ old('nama', 'Denah Semester ' . $kelas->semester) }}" required>
            @error('nama')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="nb-label" for="template">Template</label>
            <select class="nb-input" id="template" name="template">
                <option value="baris">Berbaris</option>
                <option value="pasangan">Berpasangan</option>
                <option value="u">Huruf U</option>
                <option value="kosong">Kosong</option>
            </select>
        </div>

        <div>
            <label class="nb-label" for="lebar">Kolom</label>
            <input class="nb-input" type="number" id="lebar" name="lebar" min="3" max="16" value="{{ old('lebar', 8) }}">
        </div>

        <div>
            <label class="nb-label" for="tinggi">Baris</label>
            <input class="nb-input" type="number" id="tinggi" name="tinggi" min="3" max="12" value="{{ old('tinggi', 5) }}">
        </div>

        <button class="nb-btn md:col-span-5">+ Buat Denah</button>
    </form>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($denahs as $d)
            <div class="nb-card muncul" style="animation-delay: {{ $loop->index * 60 }}ms">
                <h2 class="text-xl">{{ $d->nama }}</h2>
                <p>{{ $d->lebar }} x {{ $d->tinggi }} kotak &middot; {{ $d->mejas_count }} meja</p>

                <div class="flex flex-wrap gap-2 mt-4">
                    <a class="nb-btn biru" href="{{ route('denah.edit', $d) }}">Edit / Acak</a>
                    <a class="nb-btn putih" href="{{ route('denah.publik', $d->token) }}" target="_blank">Tampilkan</a>
                </div>

                <form method="POST" action="{{ route('denah.destroy', $d) }}" class="mt-3" onsubmit="return confirm('Hapus denah ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="font-bold underline">Hapus</button>
                </form>
            </div>
        @empty
            <div class="nb-card sm:col-span-2 lg:col-span-3">
                <p class="font-bold">Belum ada denah.</p>
                <p>Pilih template di atas lalu buat denah pertama.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>