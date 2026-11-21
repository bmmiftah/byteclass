<x-guru-layout judul="Jurnal Mengajar">
    <h1 class="text-3xl mb-6">Jurnal Mengajar</h1>

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

    @if ($kelas->isEmpty())
        <div class="nb-card mb-8">
            <p class="font-bold">Buat kelas dulu sebelum menulis jurnal.</p>
        </div>
    @else
        <form method="POST" action="{{ route('jurnal.store') }}" class="nb-card mb-8 grid gap-4 md:grid-cols-6">
            @include('jurnal._form', ['jurnal' => $baru])
            <button class="nb-btn md:col-span-6">Simpan jurnal</button>
        </form>
    @endif

    {{-- Filter --}}
    <form method="GET" action="{{ route('jurnal.index') }}" class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="nb-label" for="f_kelas">Kelas</label>
            <select class="nb-input" id="f_kelas" name="kelas_id">
                <option value="">Semua</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}" @selected(request('kelas_id') == $k->id)>
                        {{ $k->nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="nb-label" for="f_bulan">Bulan</label>
            <input class="nb-input" type="month" id="f_bulan" name="bulan" value="{{ request('bulan') }}">
        </div>

        <button class="nb-btn biru">Filter</button>
        <a
            class="nb-btn putih"
            href="{{ route('jurnal.index', array_merge(request()->only('kelas_id', 'bulan'), ['unduh' => 1])) }}"
        >
            Unduh Excel
        </a>
    </form>

    <div class="space-y-3">
        @forelse ($jurnals as $j)
            <div class="nb-card muncul">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="nb-chip" style="background: var(--kuning)">
                        {{ $j->tanggal->translatedFormat('d M Y') }}
                    </span>
                    <span class="nb-chip">{{ $j->kelas?->nama }}</span>
                    @if ($j->mapel)
                        <span class="nb-chip" style="background: var(--biru)">{{ $j->mapel->nama }}</span>
                    @endif
                </div>

                <p class="font-bold text-lg">{{ $j->topik }}</p>

                @if ($j->kegiatan)
                    <p class="mt-1">{{ $j->kegiatan }}</p>
                @endif

                @if ($j->kendala)
                    <p class="mt-1 text-sm"><b>Kendala:</b> {{ $j->kendala }}</p>
                @endif

                @if ($j->tindak_lanjut)
                    <p class="mt-1 text-sm"><b>Tindak lanjut:</b> {{ $j->tindak_lanjut }}</p>
                @endif

                <div class="flex items-center gap-4 mt-3">
                    <a class="nb-btn biru" href="{{ route('jurnal.edit', $j) }}">Edit</a>
                    <form
                        method="POST"
                        action="{{ route('jurnal.destroy', $j) }}"
                        onsubmit="return confirm('Hapus jurnal ini?')"
                    >
                        @csrf
                        @method('DELETE')
                        <button class="font-bold underline">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="nb-card">
                <p>Belum ada jurnal.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $jurnals->links() }}</div>
</x-guru-layout>