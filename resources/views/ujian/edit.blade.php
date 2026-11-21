<x-guru-layout :judul="'Atur '.$ujian->judul">
    <a href="{{ route('ujian.index', $ujian->kelas_id) }}" class="font-bold underline">
        &larr; Ujian {{ $ujian->kelas->nama }}
    </a>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3 mb-6">
        <h1 class="text-3xl">{{ $ujian->judul }}</h1>
        <span class="nb-chip">{{ $ujian->labelStatus() }}</span>
    </div>

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

    {{-- Token dan status --}}
    <div class="nb-card mb-6 grid gap-4 md:grid-cols-3" style="background: var(--kuning)">
        <div>
            <p class="font-bold">Token ujian</p>
            <p class="judul text-4xl bg-white border-2 border-black px-3 py-1 inline-block">
                {{ $ujian->token }}
            </p>
            <form method="POST" action="{{ route('ujian.token', $ujian) }}" class="mt-2">
                @csrf
                <button class="underline font-bold">Ganti token</button>
            </form>
        </div>

        <div class="md:col-span-2 space-y-2">
            <p class="font-bold">Status</p>
            <div class="flex flex-wrap gap-2">
                @foreach ([
                    'buka' => ['Buka ujian', 'hijau'],
                    'tutup' => ['Tutup ujian', 'merah'],
                    'draft' => ['Jadikan draft', 'putih'],
                ] as $st => [$label, $kelasTombol])
                    <form
                        method="POST"
                        action="{{ route('ujian.status', $ujian) }}"
                        @if ($st === 'tutup')
                            onsubmit="return confirm('Tutup ujian? Semua siswa yang sedang mengerjakan langsung dikumpulkan.')"
                        @endif
                    >
                        @csrf
                        <input type="hidden" name="status" value="{{ $st }}">
                        <button class="nb-btn {{ $kelasTombol }}">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
            <p class="text-sm">
                Umumkan token di kelas setelah ujian dibuka. Siswa masuk lewat <b>{{ url('/cbt') }}</b>.
            </p>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('ujian.update', $ujian) }}"
        class="grid gap-6 lg:grid-cols-3"
        x-data="{ mapel: '' }"
    >
        @csrf
        @method('PUT')

        {{-- Pengaturan --}}
        <div class="nb-card space-y-4 lg:col-span-1 self-start">
            <h2 class="text-lg">Pengaturan</h2>

            <div>
                <label class="nb-label" for="judul">Judul</label>
                <input
                    class="nb-input"
                    id="judul"
                    name="judul"
                    value="{{ old('judul', $ujian->judul) }}"
                    required
                >
            </div>

            <div>
                <label class="nb-label" for="durasi">Durasi (menit)</label>
                <input
                    class="nb-input"
                    type="number"
                    id="durasi"
                    name="durasi"
                    min="1"
                    max="300"
                    value="{{ old('durasi', $ujian->durasi) }}"
                    required
                >
            </div>

            <div>
                <label class="nb-label" for="mapel_id">Mapel</label>
                <select class="nb-input" id="mapel_id" name="mapel_id">
                    <option value="">-</option>
                    @foreach ($mapels as $m)
                        <option value="{{ $m->id }}" @selected(old('mapel_id', $ujian->mapel_id) == $m->id)>
                            {{ $m->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="nb-label" for="buka_pada">Boleh mulai sejak (opsional)</label>
                <input
                    class="nb-input"
                    type="datetime-local"
                    id="buka_pada"
                    name="buka_pada"
                    value="{{ old('buka_pada', $ujian->buka_pada?->format('Y-m-d\TH:i')) }}"
                >
            </div>

            <div>
                <label class="nb-label" for="tutup_pada">Tutup pada (opsional)</label>
                <input
                    class="nb-input"
                    type="datetime-local"
                    id="tutup_pada"
                    name="tutup_pada"
                    value="{{ old('tutup_pada', $ujian->tutup_pada?->format('Y-m-d\TH:i')) }}"
                >
                <p class="text-xs mt-1">
                    Siswa yang mulai mendekati waktu tutup otomatis dikumpulkan saat tutup.
                </p>
            </div>

            <label class="flex items-center gap-2 font-bold">
                <input
                    type="checkbox"
                    name="acak_soal"
                    value="1"
                    class="w-5 h-5"
                    @checked(old('acak_soal', $ujian->acak_soal))
                >
                Acak urutan soal
            </label>

            <label class="flex items-center gap-2 font-bold">
                <input
                    type="checkbox"
                    name="acak_opsi"
                    value="1"
                    class="w-5 h-5"
                    @checked(old('acak_opsi', $ujian->acak_opsi))
                >
                Acak pilihan jawaban
            </label>

            <label class="flex items-center gap-2 font-bold">
                <input
                    type="checkbox"
                    name="tampil_nilai"
                    value="1"
                    class="w-5 h-5"
                    @checked(old('tampil_nilai', $ujian->tampil_nilai))
                >
                Tampilkan nilai ke siswa
            </label>

            <button class="nb-btn w-full">Simpan</button>
        </div>

        {{-- Pilih soal --}}
        <div class="lg:col-span-2 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg">Soal ({{ $terpilih->count() }} dipilih)</h2>
                <select class="nb-input" style="width: auto" x-model="mapel" aria-label="Filter mapel">
                    <option value="">Semua mapel</option>
                    @foreach ($mapels as $m)
                        <option value="{{ $m->id }}">{{ $m->nama }}</option>
                    @endforeach
                </select>
            </div>

            @if ($terkunci)
                <div class="nb-card" style="background: var(--kuning)">
                    <p class="font-bold">Sudah ada siswa yang mulai.</p>
                    <p>Daftar soal dikunci supaya nilai tidak kacau. Pengaturan lain masih bisa diubah.</p>
                </div>
            @endif

            @forelse ($soals as $s)
                <label
                    class="nb-card flex items-start gap-3 {{ $terkunci ? 'opacity-70' : 'cursor-pointer' }}"
                    x-show="!mapel || mapel == '{{ $s->mapel_id }}'"
                >
                    <input
                        type="checkbox"
                        name="soal_ids[]"
                        value="{{ $s->id }}"
                        class="w-5 h-5 mt-1"
                        @checked($terpilih->has($s->id))
                        @disabled($terkunci)
                    >

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="nb-chip" style="background: var(--kuning)">{{ $s->label() }}</span>
                            @if ($s->mapel)
                                <span class="nb-chip" style="background: var(--biru)">{{ $s->mapel->nama }}</span>
                            @endif
                        </div>
                        <p>{{ \Illuminate\Support\Str::limit($s->teks, 140) }}</p>
                    </div>

                    <div class="w-20">
                        <span class="text-xs font-bold">Bobot</span>
                        <input
                            class="nb-input"
                            type="number"
                            aria-label="Bobot soal"
                            name="bobot[{{ $s->id }}]"
                            min="1"
                            max="100"
                            value="{{ $terpilih[$s->id] ?? $s->bobot }}"
                            @disabled($terkunci)
                        >
                    </div>
                </label>
            @empty
                <div class="nb-card">
                    <p class="font-bold">Bank soal masih kosong.</p>
                    <a class="underline font-bold" href="{{ route('soal.create') }}">Buat soal dulu</a>
                </div>
            @endforelse
        </div>
    </form>

    @unless ($terkunci)
        <form
            method="POST"
            action="{{ route('ujian.destroy', $ujian) }}"
            class="mt-6"
            onsubmit="return confirm('Hapus ujian ini?')"
        >
            @csrf
            @method('DELETE')
            <button class="font-bold underline">Hapus ujian</button>
        </form>
    @endunless
</x-guru-layout>