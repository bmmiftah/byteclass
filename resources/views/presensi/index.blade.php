<x-guru-layout :judul="'Presensi ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">Presensi {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <form method="GET" action="{{ route('presensi.index', $kelas) }}" class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="nb-label" for="tanggal">Tanggal</label>
            <input class="nb-input" type="date" id="tanggal" name="tanggal" value="{{ $tanggal }}">
        </div>

        <button class="nb-btn biru">Buka</button>
        <a class="nb-btn putih" href="{{ route('presensi.rekap', $kelas) }}">Rekap bulanan</a>

        @if ($sudah)
            <span class="nb-chip" style="background: var(--hijau)">Sudah tersimpan</span>
        @else
            <span class="nb-chip" style="background: var(--kuning)">Belum disimpan</span>
        @endif
    </form>

    @if ($siswas->isEmpty())
        <div class="nb-card">
            <p class="font-bold">Belum ada siswa aktif di kelas ini.</p>
        </div>
    @else
        <form
            method="POST"
            action="{{ route('presensi.simpan', $kelas) }}"
            x-data="{
                s: {{ \Illuminate\Support\Js::from((object) $awal->all()) }},
                urut: ['H', 'S', 'I', 'A'],
                warna: { H: 'var(--hijau)', S: 'var(--kuning)', I: 'var(--biru)', A: 'var(--merah)' },
                ganti(id) {
                    const i = this.urut.indexOf(this.s[id]);
                    this.s[id] = this.urut[(i + 1) % 4];
                },
                semua(k) {
                    Object.keys(this.s).forEach(id => this.s[id] = k);
                },
                hitung(k) {
                    return Object.values(this.s).filter(v => v === k).length;
                }
            }"
        >
            @csrf
            <input type="hidden" name="tanggal" value="{{ $tanggal }}">

            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="nb-chip" style="background: var(--hijau)">H <b x-text="hitung('H')"></b></span>
                <span class="nb-chip" style="background: var(--kuning)">S <b x-text="hitung('S')"></b></span>
                <span class="nb-chip" style="background: var(--biru)">I <b x-text="hitung('I')"></b></span>
                <span class="nb-chip" style="background: var(--merah)">A <b x-text="hitung('A')"></b></span>
                <button type="button" class="nb-btn putih" @click="semua('H')">Semua hadir</button>
            </div>

            <p class="text-sm mb-3">Ketuk nama untuk mengganti status: H &rarr; S &rarr; I &rarr; A &rarr; H.</p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($siswas as $sw)
                    <button
                        type="button"
                        @click="ganti({{ $sw->id }})"
                        class="nb-card text-left flex items-center justify-between gap-3 w-full"
                        :style="'background:' + warna[s[{{ $sw->id }}]]"
                    >
                        <span class="font-bold">{{ $sw->nama }}</span>
                        <span class="judul text-3xl" x-text="s[{{ $sw->id }}]"></span>
                    </button>

                    <input type="hidden" name="status[{{ $sw->id }}]" :value="s[{{ $sw->id }}]">
                @endforeach
            </div>

            <div class="mt-6">
                <button class="nb-btn w-full md:w-auto" style="font-size: 1.1rem">Simpan presensi</button>
            </div>
        </form>
    @endif
</x-guru-layout>