<x-siswa-layout judul="Kalender" :kelas="$kelas">
    <h1 class="text-3xl mb-4">Kalender</h1>

    <x-saya-nav />

    <div x-data="{ pilih: '{{ $dipilih }}' }">
        <div class="flex items-center justify-between gap-2 mb-3">
            <a class="nb-btn putih" href="{{ route('saya.kalender', ['bulan' => $sebelum]) }}" aria-label="Bulan sebelumnya">&larr;</a>
            <h2 class="text-xl text-center">{{ $bulan->copy()->locale('id')->translatedFormat('F Y') }}</h2>
            <a class="nb-btn putih" href="{{ route('saya.kalender', ['bulan' => $sesudah]) }}" aria-label="Bulan berikutnya">&rarr;</a>
        </div>

        @unless ($bulan->isSameMonth(now()))
            <p class="text-center mb-3"><a class="underline font-bold" href="{{ route('saya.kalender') }}">Kembali ke bulan ini</a></p>
        @endunless

        {{-- Kisi bulan --}}
        <div class="grid grid-cols-7 gap-1 text-center font-bold text-sm mb-1">
            @foreach (\App\Services\KalenderKelas::HARI as $h)
                <div>{{ $h }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-1">
            @foreach ($minggu as $pekan)
                @foreach ($pekan as $hari)
                    <button
                        type="button"
                        @click="pilih = '{{ $hari['kunci'] }}'"
                        class="text-left border-2 border-black p-1 min-h-[3.5rem] md:min-h-[6rem] align-top"
                        :style="pilih === '{{ $hari['kunci'] }}' ? { boxShadow: '4px 4px 0 #111', transform: 'translate(-2px,-2px)' } : {}"
                        style="background: {{ $hari['hariIni'] ? 'var(--kuning)' : ($hari['diBulan'] ? '#fff' : '#eee') }}; {{ $hari['diBulan'] ? '' : 'opacity:.55' }}"
                        aria-label="{{ \App\Services\KalenderKelas::tanggalTeks($hari['kunci']) }}, {{ count($hari['kejadian']) }} kejadian"
                    >
                        <span class="font-bold text-sm">{{ $hari['tgl']->day }}</span>

                        {{-- HP: titik warna --}}
                        <span class="flex flex-wrap gap-1 mt-1 md:hidden">
                            @foreach (array_slice($hari['kejadian'], 0, 4) as $e)
                                <span class="inline-block w-2 h-2 border border-black" style="background: {{ $e['warna'] }}"></span>
                            @endforeach
                        </span>

                        {{-- Layar lebar: judul singkat --}}
                        <span class="hidden md:block mt-1 space-y-1">
                            @foreach (array_slice($hari['kejadian'], 0, 3) as $e)
                                <span class="block truncate text-xs px-1 border border-black" style="background: {{ $e['warna'] }}">{{ $e['judul'] }}</span>
                            @endforeach
                            @if (count($hari['kejadian']) > 3)
                                <span class="block text-xs font-bold">+{{ count($hari['kejadian']) - 3 }} lagi</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            @endforeach
        </div>

        {{-- Legenda --}}
        <div class="flex flex-wrap gap-2 mt-4 text-sm">
            @foreach (\App\Services\KalenderKelas::JENIS as [$label, $warna])
                <span class="nb-chip" style="background: {{ $warna }}">{{ $label }}</span>
            @endforeach
        </div>

        {{-- Rincian hari yang dipilih --}}
        <div class="mt-6">
            @foreach ($minggu as $pekan)
                @foreach ($pekan as $hari)
                    <div x-show="pilih === '{{ $hari['kunci'] }}'" x-cloak class="nb-card">
                        <p class="font-bold text-lg">{{ \App\Services\KalenderKelas::tanggalTeks($hari['kunci']) }}</p>

                        @forelse ($hari['kejadian'] as $e)
                            @php $tag = $e['url'] ? 'a' : 'div'; @endphp
                            <{{ $tag }} @if ($e['url']) href="{{ $e['url'] }}" @endif class="block border-2 border-black p-2 mt-2" style="background: {{ $e['warna'] }}">
                                <span class="font-bold">{{ $e['judul'] }}</span>
                                @if ($e['selesai'])
                                    <span class="nb-chip" style="background: #fff">Sudah dikumpulkan</span>
                                @endif
                                <span class="block text-sm">{{ $e['label'] }}@if ($e['jam']) &middot; {{ $e['jam'] }}@endif @if ($e['lanjutan']) &middot; lanjutan @endif</span>
                                @if ($e['keterangan'])
                                    <span class="block text-sm whitespace-pre-line">{{ $e['keterangan'] }}</span>
                                @endif
                            </{{ $tag }}>
                        @empty
                            <p class="mt-1">Tidak ada kejadian.</p>
                        @endforelse
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
    <x-kalender-langganan />
</x-siswa-layout>