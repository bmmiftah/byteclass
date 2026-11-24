<x-siswa-layout judul="Dashboard Saya" :kelas="$kelas">
    {{-- Sapaan --}}
    <div class="nb-card mb-6 muncul" style="background: {{ $kelas->warna_hex }}">
        <p class="text-sm font-bold">Dashboard saya</p>
        <h1 class="text-3xl md:text-4xl">Halo, {{ $siswa->nama_panggilan ?: strtok($siswa->nama, ' ') }}!</h1>
        <p class="mt-1">{{ $kelas->nama }} &middot; {{ $kelas->tahun_ajaran }} &middot; Semester {{ $kelas->semester }}</p>
    </div>

    <x-saya-nav />
    <x-pwa-segera />
    <x-pwa-pasang />

    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <a
            href="{{ route('siswa.kelas', $kelas->kode_publik) }}"
            class="nb-card hover muncul block"
            style="background: #fff; animation-delay: 60ms"
        >
            <p class="judul text-4xl">&rarr;</p>
            <p class="font-bold">Materi kelas</p>
        </a>

        <a
            href="{{ route('cbt.daftar') }}"
            class="nb-card hover muncul block"
            style="background: var(--hijau); animation-delay: 120ms"
        >
            <p class="judul text-4xl">{{ $ujianBuka }}</p>
            <p class="font-bold">Ujian yang dibuka</p>
        </a>

        <a
            href="{{ route('saya.tugas') }}"
            class="nb-card hover muncul block"
            style="background: var(--kuning); animation-delay: 180ms"
        >
            <p class="judul text-4xl">{{ $tugasPerlu->count() }}</p>
            <p class="font-bold">Tugas yang perlu dikerjakan</p>
            @if ($tugasPerlu->isNotEmpty())
                <p class="text-sm">Terdekat: {{ $tugasPerlu->first()->judul }} ({{ $tugasPerlu->first()->sisaWaktu() }})</p>
            @endif
        </a>
    </div>

    <div class="grid gap-6 md:grid-cols-2 mb-8">
        {{-- Pengumuman --}}
        <section>
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-xl">Pengumuman</h2>
                <a href="{{ route('saya.pengumuman') }}" class="underline font-bold text-sm">Semua</a>
            </div>

            <div class="space-y-3">
                @forelse ($pengumuman as $p)
                    <div class="nb-card" style="background: {{ $p->disematkan ? 'var(--kuning)' : '#fff' }}">
                        <p class="font-bold">
                            @if ($p->disematkan)
                                <span aria-label="Disematkan">&#9733;</span>
                            @endif
                            {{ $p->judul }}
                            @if ($p->baru)
                                <span class="nb-chip" style="background: var(--pink)">Baru</span>
                            @endif
                        </p>
                        <p class="text-sm mt-1 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($p->isi, 160) }}</p>
                    </div>
                @empty
                    <div class="nb-card">
                        <p>Belum ada pengumuman.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- 7 hari ke depan --}}
        <section>
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-xl">7 hari ke depan</h2>
                <a href="{{ route('saya.agenda') }}" class="underline font-bold text-sm">Agenda</a>
            </div>

            <div class="space-y-3">
                @forelse ($mendatang as $e)
                    @php $tag = $e['url'] ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if ($e['url']) href="{{ $e['url'] }}" @endif class="nb-card block">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-bold">{{ $e['judul'] }}</p>
                            <span class="nb-chip" style="background: {{ $e['warna'] }}">{{ $e['label'] }}</span>
                        </div>
                        <p class="text-sm">{{ \App\Services\KalenderKelas::tanggalTeks($e['tanggal']) }}@if ($e['jam']) &middot; {{ $e['jam'] }}@endif</p>
                    </{{ $tag }}>
                @empty
                    <div class="nb-card">
                        <p>Tidak ada tenggat atau acara dalam 7 hari ke depan.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <x-pwa-pengingat />

    <form method="POST" action="{{ route('saya.keluar') }}" onsubmit="window.bersihkanCacheSaya && window.bersihkanCacheSaya()">
        @csrf
        <button class="nb-btn putih">Keluar</button>
    </form>
</x-siswa-layout>