<x-guru-layout :judul="$soal->exists ? 'Edit Soal' : 'Soal Baru'">
    <a href="{{ route('soal.index') }}" class="font-bold underline">
        &larr; Bank Soal
    </a>

    <h1 class="text-3xl mt-3 mb-6">
        {{ $soal->exists ? 'Edit Soal' : 'Soal Baru' }}
    </h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">
            {{ session('ok') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    @if (!empty($terkunci))
        <div class="nb-card mb-4" style="background: var(--kuning)">
            <p class="font-bold">Soal ini sudah dikerjakan siswa, jadi tidak bisa diubah.</p>
            <p>Klik tombol "Salin soal" di bawah untuk membuat versi baru.</p>
        </div>
    @endif

    <form
        method="POST"
        enctype="multipart/form-data"
        class="nb-card grid gap-4 md:grid-cols-6"
        action="{{ $soal->exists ? route('soal.update', $soal) : route('soal.store') }}"
        x-data="{ tipe: '{{ old('tipe', $soal->tipe) }}' }"
    >
        @csrf
        @if ($soal->exists)
            @method('PUT')
        @endif

        <div class="md:col-span-2">
            <label class="nb-label" for="tipe">Jenis soal</label>
            <select class="nb-input" id="tipe" name="tipe" x-model="tipe">
                <option value="pg">Pilihan ganda</option>
                <option value="isian">Isian singkat</option>
                <option value="esai">Uraian</option>
            </select>
        </div>

        <div class="md:col-span-3">
            <label class="nb-label" for="mapel_id">Mapel</label>
            <select class="nb-input" id="mapel_id" name="mapel_id">
                <option value="">-</option>
                @foreach ($mapels as $m)
                    <option value="{{ $m->id }}" @selected(old('mapel_id', $soal->mapel_id) == $m->id)>
                        {{ $m->nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="nb-label" for="bobot">Bobot</label>
            <input
                class="nb-input"
                type="number"
                id="bobot"
                name="bobot"
                min="1"
                max="100"
                value="{{ old('bobot', $soal->bobot ?? 1) }}"
                required
            >
        </div>

        <div class="md:col-span-6">
            <label class="nb-label" for="teks">Teks soal</label>
            <textarea class="nb-input" id="teks" name="teks" rows="4" required>{{ old('teks', $soal->teks) }}</textarea>
            <p class="text-xs mt-1">
                Teks biasa (baris baru dipertahankan). Rumus tulis dengan teks, mis. v = s / t.
            </p>
        </div>

        <div class="md:col-span-6">
            <label class="nb-label" for="gambar">Gambar (opsional, maks 5 MB)</label>
            @if ($soal->gambar)
                <img
                    src="{{ route('soal.gambar', $soal) }}"
                    alt="Gambar soal"
                    class="gambar-materi mb-2"
                    style="max-height: 200px"
                >
                <label class="flex items-center gap-2 mb-2">
                    <input type="checkbox" name="hapus_gambar" value="1" class="w-5 h-5">
                    Hapus gambar ini
                </label>
            @endif
            <input
                class="nb-input bg-white"
                type="file"
                id="gambar"
                name="gambar"
                accept=".jpg,.jpeg,.png,.webp,.gif"
            >
        </div>

        {{-- Pilihan ganda --}}
        <div class="md:col-span-6 space-y-3" x-show="tipe === 'pg'">
            <p class="font-bold">Pilihan jawaban (kosongkan yang tidak dipakai, pilih yang benar)</p>
            @foreach (['A', 'B', 'C', 'D', 'E'] as $h)
                <div class="flex items-center gap-3">
                    <input
                        type="radio"
                        name="kunci_pg"
                        value="{{ $h }}"
                        class="w-5 h-5"
                        aria-label="Kunci {{ $h }}"
                        @checked(old('kunci_pg', $soal->tipe === 'pg' ? $soal->kunci : '') === $h)
                    >
                    <span class="judul w-6">{{ $h }}</span>
                    <input
                        class="nb-input flex-1"
                        name="opsi[{{ $h }}]"
                        maxlength="500"
                        value="{{ old('opsi.'.$h, $soal->opsi[$h] ?? '') }}"
                    >
                </div>
            @endforeach
        </div>

        {{-- Isian --}}
        <div class="md:col-span-6" x-show="tipe === 'isian'" style="display: none">
            <label class="nb-label" for="kunci_isian">Jawaban benar</label>
            <input
                class="nb-input"
                id="kunci_isian"
                name="kunci_isian"
                maxlength="500"
                value="{{ old('kunci_isian', $soal->tipe === 'isian' ? $soal->kunci : '') }}"
            >
            <p class="text-xs mt-1">
                Boleh lebih dari satu, pisahkan dengan <code>|</code>, mis. <code>fotosintesis | proses fotosintesis</code>.
                Huruf besar/kecil dan spasi berlebih diabaikan.
            </p>
        </div>

        {{-- Uraian --}}
        <div class="md:col-span-6" x-show="tipe === 'esai'" style="display: none">
            <label class="nb-label" for="kunci_esai">Pedoman jawaban (untuk guru, tidak terlihat siswa)</label>
            <textarea
                class="nb-input"
                id="kunci_esai"
                name="kunci_esai"
                rows="3"
            >{{ old('kunci_esai', $soal->tipe === 'esai' ? $soal->kunci : '') }}</textarea>
            <p class="text-xs mt-1">
                Uraian dikoreksi manual oleh guru setelah siswa mengirim.
            </p>
        </div>

        <button class="nb-btn md:col-span-6" @disabled(!empty($terkunci))>
            Simpan soal
        </button>
    </form>

    @if ($soal->exists)
        <div class="flex flex-wrap items-center gap-4 mt-6">
            <form method="POST" action="{{ route('soal.salin', $soal) }}">
                @csrf
                <button class="nb-btn putih">Salin soal</button>
            </form>

            <form
                method="POST"
                action="{{ route('soal.destroy', $soal) }}"
                onsubmit="return confirm('Hapus soal ini?')"
            >
                @csrf
                @method('DELETE')
                <button class="font-bold underline">Hapus soal</button>
            </form>
        </div>
    @endif
</x-guru-layout>