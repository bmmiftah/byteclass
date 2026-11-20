<x-guru-layout :judul="'Edit ' . $item->judul">
    <a href="{{ route('materi.edit', $bab) }}" class="font-bold underline">&larr; {{ $bab->judul }}</a>

    <div class="flex items-center gap-3 mt-3 mb-6">
        <span class="ikon-tipe">{{ $item->label() }}</span>
        <h1 class="text-3xl">Edit materi</h1>
    </div>

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('materi.item.update', $item) }}" class="nb-card space-y-4 max-w-4xl">
        @csrf
        @method('PUT')

        <div>
            <label class="nb-label" for="judul">Judul materi</label>
            <input class="nb-input" id="judul" name="judul" value="{{ old('judul', $item->judul) }}" required>
        </div>

        @if ($item->tipe === 'file')
            <p class="text-sm">
                File: <b>{{ $item->nama_asli }}</b> ({{ \Illuminate\Support\Number::fileSize($item->ukuran ?? 0, 1) }}).
                Untuk mengganti file, tambahkan materi baru lalu hapus yang lama.
            </p>
        @elseif (in_array($item->tipe, ['link', 'video']))
            <div>
                <label class="nb-label" for="url">Alamat (URL)</label>
                <input class="nb-input" type="url" id="url" name="url" value="{{ old('url', $item->url) }}" required>
            </div>
        @endif

        <div>
            <label class="nb-label">{{ $item->tipe === 'teks' ? 'Isi teks' : 'Penjelasan (opsional)' }}</label>
            <x-editor-teks nama="isi" :nilai="old('isi', $item->isi_html)" />
            @error('isi')
                <p class="nb-error mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-3">
            <button class="nb-btn">Simpan</button>
            <a class="nb-btn putih" href="{{ route('materi.edit', $bab) }}">Batal</a>
        </div>
    </form>
</x-guru-layout>