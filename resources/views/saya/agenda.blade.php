<x-siswa-layout judul="Agenda" :kelas="$kelas">
    <h1 class="text-3xl mb-4">Agenda</h1>

    <x-saya-nav />

    <h2 class="text-xl mb-2">30 hari ke depan</h2>

    <div class="space-y-4 mb-8">
        @forelse ($akan as $tanggal => $daftar)
            <div class="nb-card">
                <p class="font-bold text-lg">
                    {{ \App\Services\KalenderKelas::tanggalTeks($tanggal) }}
                    @if (\Illuminate\Support\Carbon::parse($tanggal)->isToday())
                        <span class="nb-chip" style="background: var(--kuning)">Hari ini</span>
                    @elseif (\Illuminate\Support\Carbon::parse($tanggal)->isTomorrow())
                        <span class="nb-chip" style="background: var(--kuning)">Besok</span>
                    @endif
                </p>

                @foreach ($daftar as $e)
                    @php $tag = $e['url'] ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if ($e['url']) href="{{ $e['url'] }}" @endif class="flex flex-wrap items-center justify-between gap-2 border-t-2 border-black pt-2 mt-2">
                        <span>
                            <span class="font-bold">{{ $e['judul'] }}</span>
                            @if ($e['selesai'])
                                <span class="nb-chip" style="background: #fff">Sudah dikumpulkan</span>
                            @endif
                            <span class="block text-sm">@if ($e['jam']) {{ $e['jam'] }} @else Sepanjang hari @endif @if ($e['lanjutan']) &middot; lanjutan @endif</span>
                            @if ($e['keterangan'])
                                <span class="block text-sm whitespace-pre-line">{{ $e['keterangan'] }}</span>
                            @endif
                        </span>
                        <span class="nb-chip" style="background: {{ $e['warna'] }}">{{ $e['label'] }}</span>
                    </{{ $tag }}>
                @endforeach
            </div>
        @empty
            <div class="nb-card">
                <p class="font-bold">Tidak ada agenda dalam 30 hari ke depan.</p>
            </div>
        @endforelse
    </div>

    @if ($lalu->isNotEmpty())
        <h2 class="text-xl mb-2">7 hari terakhir</h2>

        <div class="space-y-4 mb-8">
            @foreach ($lalu as $tanggal => $daftar)
                <div class="nb-card" style="background: #f4f4f4">
                    <p class="font-bold">{{ \App\Services\KalenderKelas::tanggalTeks($tanggal) }}</p>

                    @foreach ($daftar as $e)
                        <p class="text-sm mt-1"><span class="nb-chip" style="background: {{ $e['warna'] }}">{{ $e['label'] }}</span> {{ $e['judul'] }}@if ($e['selesai']) (sudah dikumpulkan)@endif</p>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</x-siswa-layout>