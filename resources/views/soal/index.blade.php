<x-guru-layout judul="Bank Soal">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-3xl">Bank Soal</h1>
        <a class="nb-btn" href="{{ route('soal.create') }}">+ Soal baru</a>
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

    <form method="GET" action="{{ route('soal.index') }}" class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="nb-label" for="f_mapel">Mapel</label>
            <select class="nb-input" id="f_mapel" name="mapel_id">
                <option value="">Semua</option>
                @foreach ($mapels as $m)
                    <option value="{{ $m->id }}" @selected(request('mapel_id') == $m->id)>
                        {{ $m->nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="nb-label" for="f_tipe">Jenis</label>
            <select class="nb-input" id="f_tipe" name="tipe">
                <option value="">Semua</option>
                <option value="pg" @selected(request('tipe') === 'pg')>Pilihan ganda</option>
                <option value="isian" @selected(request('tipe') === 'isian')>Isian</option>
                <option value="esai" @selected(request('tipe') === 'esai')>Uraian</option>
            </select>
        </div>

        <div>
            <label class="nb-label" for="f_q">Cari teks</label>
            <input class="nb-input" id="f_q" name="q" value="{{ request('q') }}">
        </div>

        <button class="nb-btn biru">Filter</button>
    </form>

    <div class="space-y-3">
        @forelse ($soals as $s)
            <a href="{{ route('soal.edit', $s) }}" class="nb-card hover block muncul">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="nb-chip" style="background: var(--kuning)">{{ $s->label() }}</span>

                    @if ($s->mapel)
                        <span class="nb-chip" style="background: var(--biru)">{{ $s->mapel->nama }}</span>
                    @endif

                    <span class="nb-chip">Bobot {{ $s->bobot }}</span>

                    @if ($s->ujians_count)
                        <span class="nb-chip" style="background: var(--hijau)">
                            Dipakai {{ $s->ujians_count }} ujian
                        </span>
                    @endif

                    @if ($s->gambar)
                        <span class="nb-chip">Gambar</span>
                    @endif
                </div>

                <p>{{ \Illuminate\Support\Str::limit($s->teks, 160) }}</p>
            </a>
        @empty
            <div class="nb-card">
                <p class="font-bold">Belum ada soal.</p>
                <p>Mulai dengan tombol "+ Soal baru".</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $soals->links() }}</div>
</x-guru-layout>