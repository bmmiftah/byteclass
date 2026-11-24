<x-guru-layout :judul="$a->exists ? 'Ubah agenda' : 'Agenda baru'">
    <a href="{{ route('agenda.index', $kelas) }}" class="font-bold underline">&larr; Agenda {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">{{ $a->exists ? 'Ubah agenda' : 'Agenda baru' }} &middot; {{ $kelas->nama }}</h1>

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ $a->exists ? route('agenda.update', $a) : route('agenda.store', $kelas) }}"
        class="nb-card grid gap-4 md:grid-cols-2"
    >
        @csrf
        @if ($a->exists)
            @method('PUT')
        @endif

        <div class="md:col-span-2">
            <label class="nb-label" for="judul">Judul</label>
            <input class="nb-input" id="judul" name="judul" maxlength="150" value="{{ old('judul', $a->judul) }}" required>
        </div>

        <div>
            <label class="nb-label" for="jenis">Jenis</label>
            <select class="nb-input" id="jenis" name="jenis">
                @foreach (\App\Models\Agenda::JENIS as $k => $v)
                    <option value="{{ $k }}" @selected(old('jenis', $a->jenis) === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>

        <div></div>

        <div>
            <label class="nb-label" for="tanggal_mulai">Tanggal mulai</label>
            <input
                class="nb-input"
                type="date"
                id="tanggal_mulai"
                name="tanggal_mulai"
                value="{{ old('tanggal_mulai', $a->mulai?->format('Y-m-d')) }}"
                required
            >
        </div>

        <div>
            <label class="nb-label" for="jam_mulai">Jam mulai (opsional)</label>
            <input
                class="nb-input"
                type="time"
                id="jam_mulai"
                name="jam_mulai"
                value="{{ old('jam_mulai', $a->exists && ! $a->seharian ? $a->mulai->format('H:i') : '') }}"
            >
            <p class="text-sm">Kosong = sepanjang hari.</p>
        </div>

        <div>
            <label class="nb-label" for="tanggal_selesai">Tanggal selesai (opsional)</label>
            <input
                class="nb-input"
                type="date"
                id="tanggal_selesai"
                name="tanggal_selesai"
                value="{{ old('tanggal_selesai', $a->selesai?->format('Y-m-d')) }}"
            >
            <p class="text-sm">Isi kalau acaranya beberapa hari, misalnya libur semester.</p>
        </div>

        <div>
            <label class="nb-label" for="jam_selesai">Jam selesai (opsional)</label>
            <input
                class="nb-input"
                type="time"
                id="jam_selesai"
                name="jam_selesai"
                value="{{ old('jam_selesai', $a->exists && ! $a->seharian && $a->selesai ? $a->selesai->format('H:i') : '') }}"
            >
        </div>

        <div class="md:col-span-2">
            <label class="nb-label" for="keterangan">Keterangan (opsional)</label>
            <textarea class="nb-input" id="keterangan" name="keterangan" rows="3" maxlength="1000">{{ old('keterangan', $a->keterangan) }}</textarea>
        </div>

        <div class="md:col-span-2">
            <button class="nb-btn kuning">Simpan</button>
        </div>
    </form>
</x-guru-layout>