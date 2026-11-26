@php
    $tglId = fn ($d) => $d->locale('id')->translatedFormat('j M Y');
    $diraih = collect($lencana)->filter(fn ($l) => $l['diraih'])->count();
@endphp

<x-siswa-layout judul="Poin Saya" :kelas="$kelas">
    <h1 class="text-3xl mb-4">Poin saya</h1>
    <x-saya-nav />

    {{-- Poin saya --}}
    <div class="grid gap-4 sm:grid-cols-2 mb-8">
        <div class="nb-card muncul" style="background: var(--kuning)">
            <p class="font-bold">Poin usahaku</p>
            <p class="judul text-6xl">{{ $total }}</p>
            <p class="text-sm">Dihitung sejak {{ $tglId($p->mulai_pada) }}. Poin diberi untuk usaha, bukan untuk nilai.</p>
        </div>
        <div class="nb-card muncul" style="background: var(--biru); animation-delay: 60ms">
            <p class="font-bold">Lencanaku</p>
            <p class="judul text-6xl">{{ $diraih }}<span class="text-2xl"> / {{ count($lencana) }}</span></p>
            <p class="text-sm">Lencana didapat otomatis saat syaratnya terpenuhi.</p>
        </div>
    </div>

    {{-- Target kelas --}}
    @if ($target)
        <section class="nb-card mb-8 space-y-2" style="background: {{ $target['tercapai'] ? 'var(--hijau)' : '#fff' }}">
            <h2 class="text-xl">Target kelas{{ $target['judul'] ? ': ' . $target['judul'] : '' }}</h2>
            <div class="border-2 border-black bg-white h-7" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $target['persen'] }}" aria-label="Kemajuan target kelas">
                <div class="h-full" style="width: {{ $target['persen'] }}%; background: {{ $target['tercapai'] ? 'var(--hijau)' : 'var(--oranye)' }}"></div>
            </div>
            <p>
                <b>{{ $target['capai'] }}</b> dari {{ $target['target'] }} poin ({{ $target['persen'] }}%)
                @if ($target['tercapai'])
                    &middot; <b>Target tercapai! Kerja bagus, semuanya.</b>
                @else
                    &middot; sisa {{ $target['target'] - $target['capai'] }} poin. Semua poin kelas dihitung bersama.
                @endif
            </p>
        </section>
    @endif

    {{-- Lencana --}}
    <section class="mb-8">
        <h2 class="text-xl mb-2">Lencana</h2>
        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
            @foreach ($lencana as $l)
                <div class="nb-card text-center {{ $l['diraih'] ? '' : 'opacity-60' }}" style="background: {{ $l['diraih'] ? 'var(--pink)' : '#eee' }}">
                    <p class="text-4xl" aria-hidden="true">{{ $l['diraih'] ? $l['ikon'] : '' }}</p>
                    <p class="font-bold">{{ $l['nama'] }}</p>
                    <p class="text-sm">{{ $l['syarat'] }}</p>
                    @if ($l['diraih'])
                        <p class="text-xs mt-1">Diraih {{ $tglId($l['diraih']) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Tim --}}
    @if ($tim)
        <section class="mb-8">
            <h2 class="text-xl mb-2">Papan tim</h2>
            <div class="space-y-3">
                @foreach ($tim as $t)
                    @php $saya = $t['id'] === $timku; @endphp
                    <div class="nb-card" style="background: {{ $saya ? 'var(--kuning)' : '#fff' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-bold text-lg">
                                {{ $t['peringkat'] }}. {{ $t['nama'] }}
                                @if ($saya) <span class="nb-chip" style="background: var(--pink)">Timku</span> @endif
                            </p>
                            <p><b>{{ number_format($t['rata'], 1, ',', '') }}</b> <span class="text-sm">rata-rata per anggota</span></p>
                        </div>
                        @if ($saya)
                            <p class="text-sm mt-1">
                                @foreach ($t['anggota'] as $a)
                                    {{ $a['nama'] }} ({{ $a['poin'] }}){{ ! $loop->last ? ',' : '' }}
                                @endforeach
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="text-sm mt-2">Skor tim = rata-rata poin anggotanya sejak putaran ini dimulai, jadi tiap orang berarti.</p>
        </section>
    @endif

    {{-- 5 besar --}}
    @if ($lima)
        <section class="mb-8">
            <h2 class="text-xl mb-2">5 besar kelas</h2>
            <div class="space-y-2">
                @foreach ($lima as $b)
                    <div class="nb-card flex items-center justify-between" style="background: {{ $b['saya'] ? 'var(--kuning)' : '#fff' }}">
                        <p class="font-bold">{{ $b['peringkat'] }}. {{ $b['nama'] }} @if ($b['saya']) <span class="nb-chip" style="background: var(--pink)">Aku</span> @endif</p>
                        <p><b>{{ $b['total'] }}</b> poin</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Riwayat --}}
    <section class="mb-8">
        <h2 class="text-xl mb-2">Dari mana poinku</h2>
        <div class="space-y-2">
            @forelse ($riwayat as $r)
                <div class="nb-card flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-bold">{{ $r['label'] }}</p>
                        @if ($r['keterangan'])
                            <p class="text-sm">{{ $r['keterangan'] }}</p>
                        @endif
                        <p class="text-xs">{{ $tglId($r['tanggal']) }}</p>
                    </div>
                    <p class="judul text-2xl">{{ $r['poin'] > 0 ? '+' : '' }}{{ $r['poin'] }}</p>
                </div>
            @empty
                <div class="nb-card"><p>Belum ada poin. Kumpulkan tugas, kerjakan ujian, atau hadir di kelas untuk memulai.</p></div>
            @endforelse
        </div>
    </section>

    <section class="nb-card mb-8">
        <h2 class="text-xl mb-1">Cara dapat poin</h2>
        <ul class="text-sm space-y-1 list-disc pl-5">
            <li>Mengumpulkan tugas tepat waktu: +{{ \App\Services\Gamifikasi::POIN['tugas_tepat'] }}, terlambat: +{{ \App\Services\Gamifikasi::POIN['tugas_telat'] }}</li>
            <li>Menyelesaikan ujian: +{{ \App\Services\Gamifikasi::POIN['ujian'] }}</li>
            <li>Hadir di kelas: +{{ \App\Services\Gamifikasi::POIN['hadir'] }} per hari, dan +{{ \App\Services\Gamifikasi::POIN['pekan'] }} bila hadir penuh sepekan</li>
            <li>Dari guru, untuk hal baik yang tidak tercatat otomatis</li>
        </ul>
    </section>
</x-siswa-layout>