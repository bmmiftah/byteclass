<x-guru-layout :judul="$p->exists ? 'Ubah pengumuman' : 'Pengumuman baru'">
    <a href="{{ route('pengumuman.index', $kelas) }}" class="font-bold underline">&larr; Pengumuman {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">{{ $p->exists ? 'Ubah pengumuman' : 'Pengumuman baru' }} &middot; {{ $kelas->nama }}</h1>

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ $p->exists ? route('pengumuman.update', $p) : route('pengumuman.store', $kelas) }}"
        class="nb-card grid gap-4 md:grid-cols-2"
    >
        @csrf
        @if ($p->exists)
            @method('PUT')
        @endif

        <div class="md:col-span-2">
            <label class="nb-label" for="judul">Judul</label>
            <input class="nb-input" id="judul" name="judul" maxlength="150" value="{{ old('judul', $p->judul) }}" required>
        </div>

        <div class="md:col-span-2">
            <label class="nb-label" for="isi">Isi pengumuman</label>
            <textarea class="nb-input" id="isi" name="isi" rows="7" maxlength="3000" required>{{ old('isi', $p->isi) }}</textarea>
            <p class="text-sm">Teks biasa. Baris baru dipertahankan.</p>
        </div>

        <div>
            <label class="nb-label" for="terbit_pada">Mulai tayang (opsional)</label>
            <input
                class="nb-input"
                type="datetime-local"
                id="terbit_pada"
                name="terbit_pada"
                value="{{ old('terbit_pada', $p->terbit_pada?->format('Y-m-d\TH:i')) }}"
            >
            <p class="text-sm">Kosong = langsung tayang.</p>
        </div>

        <div>
            <label class="nb-label" for="berakhir_pada">Berakhir (opsional)</label>
            <input
                class="nb-input"
                type="datetime-local"
                id="berakhir_pada"
                name="berakhir_pada"
                value="{{ old('berakhir_pada', $p->berakhir_pada?->format('Y-m-d\TH:i')) }}"
            >
            <p class="text-sm">Kosong = tayang terus sampai lu hapus.</p>
        </div>

        <label class="flex items-center gap-2 font-bold md:col-span-2">
            <input type="checkbox" name="disematkan" value="1" @checked(old('disematkan', $p->disematkan))>
            Sematkan di atas daftar
        </label>

        <div class="md:col-span-2">
            <button class="nb-btn kuning">Simpan</button>
        </div>
    </form>
</x-guru-layout>