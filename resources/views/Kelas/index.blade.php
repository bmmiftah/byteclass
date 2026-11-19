<x-guru-layout judul="Kelas">
    <h1 class="text-3xl mb-6">Kelas Saya</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('kelas.store') }}" class="nb-card mb-8 grid gap-4 md:grid-cols-4 items-end">
        @csrf

        <div class="md:col-span-2">
            <label class="nb-label" for="nama">Nama kelas</label>
            <input class="nb-input" id="nama" name="nama" value="{{ old('nama') }}" placeholder="mis. X IPA 2" required>
            @error('nama')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="nb-label" for="tahun_ajaran">Tahun ajaran</label>
            <input class="nb-input" id="tahun_ajaran" name="tahun_ajaran" value="{{ old('tahun_ajaran', '2026/2027') }}" required>
            @error('tahun_ajaran')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="nb-label" for="semester">Semester</label>
            <select class="nb-input" id="semester" name="semester">
                <option value="1">1 (Ganjil)</option>
                <option value="2">2 (Genap)</option>
            </select>
        </div>

        <button class="nb-btn md:col-span-4">+ Tambah Kelas</button>
    </form>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($kelas as $k)
            @php
                $warna = ['var(--kuning)', 'var(--biru)', 'var(--pink)', 'var(--hijau)', 'var(--oranye)'][$loop->index % 5];
            @endphp
            <a
                href="{{ route('kelas.show', $k) }}"
                class="nb-card hover muncul block"
                style="background: {{ $warna }}; animation-delay: {{ $loop->index * 60 }}ms"
            >
                <h2 class="text-2xl">{{ $k->nama }}</h2>
                <p>{{ $k->tahun_ajaran }} &middot; Semester {{ $k->semester }}</p>
                <p class="mt-3 font-bold">{{ $k->siswas_count }} siswa</p>
            </a>
        @empty
            <div class="nb-card sm:col-span-2 lg:col-span-3">
                <p class="font-bold">Belum ada kelas.</p>
                <p>Isi formulir di atas untuk menambah kelas pertama.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>