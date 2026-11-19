<x-guru-layout :judul="'Kelompok '.$kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>
    <h1 class="text-3xl mt-3 mb-6">Kelompok {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('kelompok.store', $kelas) }}" class="nb-card mb-8 grid gap-4 md:grid-cols-6 items-end">
        @csrf
        <div class="md:col-span-3">
            <label class="nb-label" for="nama">Nama sesi / kegiatan</label>
            <input class="nb-input" id="nama" name="nama" value="{{ old('nama') }}" placeholder="mis. Projek IPA Bab 3" required>
            @error('nama') <p class="nb-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="nb-label" for="berdasarkan">Bagi berdasarkan</label>
            <select class="nb-input" id="berdasarkan" name="berdasarkan">
                <option value="jumlah">Jumlah kelompok</option>
                <option value="anggota">Anggota per kelompok</option>
            </select>
        </div>
        <div>
            <label class="nb-label" for="nilai">Angkanya</label>
            <input class="nb-input" type="number" id="nilai" name="nilai" min="1" max="40" value="{{ old('nilai', 6) }}">
        </div>
        <div>
            <label class="nb-label" for="metode">Cara</label>
            <select class="nb-input" id="metode" name="metode">
                <option value="acak">Acak</option>
                <option value="campur_gender">Campur L/P</option>
                <option value="kosong">Manual (kosong)</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="nb-label" for="tema">Nama kelompok</label>
            <select class="nb-input" id="tema" name="tema">
                <option value="angka">Kelompok 1, 2, 3...</option>
                <option value="warna">Warna</option>
                <option value="hewan">Hewan</option>
                <option value="planet">Planet</option>
                <option value="buah">Buah</option>
            </select>
        </div>
        <button class="nb-btn md:col-span-4">+ Buat Pembagian Kelompok</button>
    </form>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($sesis as $s)
            <div class="nb-card muncul" style="animation-delay: {{ $loop->index * 60 }}ms">
                <h2 class="text-xl">{{ $s->nama }}</h2>
                <p>{{ $s->kelompoks_count }} kelompok &middot; {{ $s->created_at->translatedFormat('d M Y') }}</p>
                <div class="flex flex-wrap gap-2 mt-4">
                    <a class="nb-btn biru" href="{{ route('kelompok.edit', $s) }}">Edit / Acak</a>
                    <a class="nb-btn putih" href="{{ route('kelompok.publik', $s->token) }}" target="_blank">Tampilkan</a>
                </div>
                <form method="POST" action="{{ route('kelompok.destroy', $s) }}" class="mt-3"
                      onsubmit="return confirm('Hapus sesi kelompok ini?')">
                    @csrf @method('DELETE')
                    <button class="font-bold underline">Hapus</button>
                </form>
            </div>
        @empty
            <div class="nb-card sm:col-span-2 lg:col-span-3">
                <p class="font-bold">Belum ada pembagian kelompok.</p>
                <p>Isi formulir di atas untuk membuat yang pertama.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>
