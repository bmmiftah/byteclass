<x-guru-layout :judul="'Materi ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>
    <h1 class="text-3xl mt-3 mb-6">Materi {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    {{-- Akses siswa --}}
    @php $linkKelas = route('siswa.kelas', $kelas->kode_publik); @endphp

    <div
        class="nb-card mb-8 grid gap-5 md:grid-cols-3"
        style="background: var(--biru)"
        x-data="{
            salin() {
                navigator.clipboard.writeText('{{ $linkKelas }}');
                this.ok = true;
                setTimeout(() => this.ok = false, 1800);
            },
            unduh() {
                const a = document.createElement('a');
                a.href = this.$refs.qr.toDataURL('image/png');
                a.download = 'qr-{{ $kelas->kode_publik }}.png';
                a.click();
            },
            ok: false
        }"
        x-init="$nextTick(() => QRCode.toCanvas($refs.qr, '{{ $linkKelas }}', { width: 176, margin: 1 }))"
    >
        <div class="md:col-span-2 space-y-3">
            <h2 class="text-xl">Akses siswa</h2>
            <p>Siswa membuka link ini, memindai QR, atau memasukkan kode di halaman <b>{{ url('/belajar') }}</b>.</p>
            <p>
                Kode kelas:
                <span class="judul text-3xl bg-white border-2 border-black px-3 py-1 inline-block">{{ $kelas->kode_publik }}</span>
            </p>
            <p class="break-all text-sm">{{ $linkKelas }}</p>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="nb-btn putih" @click="salin()" x-text="ok ? 'Tersalin' : 'Salin link'"></button>
                <button type="button" class="nb-btn putih" @click="unduh()">Unduh QR</button>
                <a class="nb-btn putih" href="{{ $linkKelas }}" target="_blank">Lihat tampilan siswa</a>
            </div>

            <form
                method="POST"
                action="{{ route('materi.kodeBaru', $kelas) }}"
                onsubmit="return confirm('Kode lama akan mati. Link dan QR yang sudah dibagikan tidak berlaku lagi. Lanjutkan?')"
            >
                @csrf
                <button class="underline font-bold">Buat kode baru</button>
            </form>
        </div>

        <div class="flex md:justify-end">
            <canvas x-ref="qr" class="border-2 border-black bg-white"></canvas>
        </div>
    </div>

    <p class="text-sm mb-4">
        Penyimpanan materi terpakai (semua kelas):
        <b>{{ \Illuminate\Support\Number::fileSize($terpakai, 1) }}</b> dari paket hosting 15 GB.
    </p>

    {{-- Tambah bab --}}
    <form method="POST" action="{{ route('materi.store', $kelas) }}" class="nb-card mb-8 grid gap-4 md:grid-cols-6 items-end">
        @csrf

        <div class="md:col-span-3">
            <label class="nb-label" for="judul">Judul bab</label>
            <input
                class="nb-input"
                id="judul"
                name="judul"
                value="{{ old('judul') }}"
                placeholder="mis. Bab 3 - Besaran dan Satuan"
                required
            >
            @error('judul')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="md:col-span-2">
            <label class="nb-label" for="mapel_id">Mata pelajaran</label>
            <select class="nb-input" id="mapel_id" name="mapel_id">
                <option value="">Umum</option>
                @foreach ($mapels as $m)
                    <option value="{{ $m->id }}" @selected(old('mapel_id') == $m->id)>{{ $m->nama }}</option>
                @endforeach
            </select>
        </div>

        <input type="hidden" name="status" value="draft">
        <button class="nb-btn md:col-span-1">+ Bab</button>
    </form>

    {{-- Daftar bab per mapel --}}
    @forelse ($babs->groupBy(fn ($b) => $b->mapel?->nama ?? 'Umum') as $namaMapel => $daftar)
        <h2 class="text-2xl mt-8 mb-3">{{ $namaMapel }}</h2>

        <div class="space-y-3">
            @foreach ($daftar as $b)
                <div
                    class="nb-card flex flex-wrap items-center gap-3 justify-between muncul"
                    style="animation-delay: {{ $loop->index * 50 }}ms"
                >
                    <div class="min-w-[200px] flex-1">
                        <p class="font-bold text-lg">{{ $b->judul }}</p>
                        <p class="text-sm">
                            {{ $b->items_count }} materi
                            @if ($b->status === 'terbit' && $b->terbit_pada && ! $b->bisaDilihat())
                                &middot; terbit {{ $b->terbit_pada->translatedFormat('d M Y H:i') }}
                            @endif
                        </p>
                    </div>

                    @php
                        $warna = ['Draft' => '#fff', 'Dijadwalkan' => 'var(--kuning)', 'Terbit' => 'var(--hijau)'][$b->labelStatus()];
                    @endphp

                    <span class="nb-chip" style="background: {{ $warna }}">{{ $b->labelStatus() }}</span>

                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('materi.pindahBab', $b) }}">
                            @csrf
                            <input type="hidden" name="arah" value="naik">
                            <button class="nb-btn putih" style="padding: .25rem .6rem" aria-label="Naik">&uarr;</button>
                        </form>

                        <form method="POST" action="{{ route('materi.pindahBab', $b) }}">
                            @csrf
                            <input type="hidden" name="arah" value="turun">
                            <button class="nb-btn putih" style="padding: .25rem .6rem" aria-label="Turun">&darr;</button>
                        </form>

                        <a class="nb-btn biru" href="{{ route('materi.edit', $b) }}">Edit</a>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="nb-card">
            <p class="font-bold">Belum ada bab.</p>
            <p>Buat bab pertama lewat formulir di atas, lalu isi dengan materi.</p>
        </div>
    @endforelse
</x-guru-layout>