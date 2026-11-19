<x-guru-layout :judul="'Penilaian ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3 mb-6">
        <h1 class="text-3xl">Penilaian {{ $kelas->nama }}</h1>
        <a href="{{ route('penilaian.rekapKelas', $kelas) }}" class="nb-btn putih">Rekap seluruh nilai</a>
    </div>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    @if ($rubriks->isEmpty())
        <div class="nb-card mb-6" style="background: var(--kuning)">
            <p class="font-bold">Kamu belum punya rubrik.</p>
            <p>
                Buat dulu di menu <a class="underline font-bold" href="{{ route('rubrik.index') }}">Rubrik</a>, lalu
                kembali ke sini.
            </p>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('penilaian.store', $kelas) }}"
        x-data="{ jenis: '{{ old('jenis', 'individu') }}' }"
        class="nb-card mb-8 grid gap-4 md:grid-cols-6 items-end"
    >
        @csrf

        <div class="md:col-span-3">
            <label class="nb-label" for="judul">Judul penilaian</label>
            <input
                class="nb-input"
                id="judul"
                name="judul"
                value="{{ old('judul') }}"
                placeholder="mis. Praktik Pengukuran"
                required
            >
            @error('judul')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-6">
            <label class="nb-label" for="materi_bab_id">Bab materi (opsional)</label>
            <select class="nb-input" id="materi_bab_id" name="materi_bab_id">
                <option value="">- tidak dikaitkan -</option>

                @foreach ($babs as $b)
                    <option value="{{ $b->id }}" @selected(old('materi_bab_id') == $b->id)>{{ $b->judul }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-3">
            <label class="nb-label" for="materi">Materi</label>
            <input
                class="nb-input"
                id="materi"
                name="materi"
                value="{{ old('materi') }}"
                placeholder="mis. Bab 3 - Besaran dan Satuan"
            >
        </div>

        <div class="md:col-span-2">
            <label class="nb-label" for="mapel_id">Mata pelajaran</label>
            <select class="nb-input" id="mapel_id" name="mapel_id">
                <option value="">-</option>
                @foreach ($mapels as $m)
                    <option value="{{ $m->id }}" @selected(old('mapel_id') == $m->id)>{{ $m->nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="nb-label" for="rubrik_id">Rubrik</label>
            <select class="nb-input" id="rubrik_id" name="rubrik_id" required>
                @foreach ($rubriks as $r)
                    <option value="{{ $r->id }}" @selected(old('rubrik_id') == $r->id)>{{ $r->nama }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="nb-label" for="tanggal">Tanggal</label>
            <input
                class="nb-input"
                type="date"
                id="tanggal"
                name="tanggal"
                value="{{ old('tanggal', now()->toDateString()) }}"
                required
            >
        </div>

        <div>
            <label class="nb-label" for="kkm">KKM</label>
            <input
                class="nb-input"
                type="number"
                id="kkm"
                name="kkm"
                min="0"
                max="100"
                value="{{ old('kkm', 75) }}"
                required
            >
        </div>

        <div class="md:col-span-2">
            <label class="nb-label" for="jenis">Jenis</label>
            <select class="nb-input" id="jenis" name="jenis" x-model="jenis">
                <option value="individu">Individu</option>
                <option value="kelompok">Kelompok</option>
            </select>
        </div>

        <div class="md:col-span-4" x-show="jenis === 'kelompok'" style="display: none">
            <label class="nb-label" for="kelompok_sesi_id">Pakai pembagian kelompok</label>
            <select class="nb-input" id="kelompok_sesi_id" name="kelompok_sesi_id">
                @forelse ($sesis as $s)
                    <option value="{{ $s->id }}">{{ $s->nama }}</option>
                @empty
                    <option value="">(belum ada sesi kelompok)</option>
                @endforelse
            </select>
            @error('kelompok_sesi_id')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <button class="nb-btn md:col-span-6" @disabled($rubriks->isEmpty())>+ Buat Penilaian dan Mulai Menilai</button>
    </form>

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($penilaians as $p)
            <div class="nb-card muncul" style="animation-delay: {{ $loop->index * 60 }}ms">
                <h2 class="text-xl">{{ $p->judul }}</h2>
                <p class="text-sm">
                    {{ $p->mapel?->nama ?? 'Tanpa mapel' }} &middot; {{ $p->tanggal->translatedFormat('d M Y') }}
                    &middot; {{ $p->jenis === 'kelompok' ? 'Kelompok' : 'Individu' }}
                </p>

                @if ($p->materi)
                    <p class="text-sm">Materi: {{ $p->materi }}</p>
                @endif

                <p class="mt-2 font-bold">{{ $p->nilais_count }} dari {{ $jumlahSiswa }} siswa sudah dinilai</p>

                <div class="flex flex-wrap gap-2 mt-4">
                    <a class="nb-btn" href="{{ route('penilaian.nilai', $p) }}">Nilai</a>
                    <a class="nb-btn putih" href="{{ route('penilaian.show', $p) }}">Rekap</a>
                </div>

                <form
                    method="POST"
                    action="{{ route('penilaian.destroy', $p) }}"
                    class="mt-3"
                    onsubmit="return confirm('Hapus penilaian ini beserta nilainya?')"
                >
                    @csrf
                    @method('DELETE')
                    <button class="font-bold underline">Hapus</button>
                </form>
            </div>
        @empty
            <div class="nb-card sm:col-span-2 lg:col-span-3">
                <p class="font-bold">Belum ada penilaian.</p>
                <p>Isi formulir di atas untuk membuat penilaian pertama.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>