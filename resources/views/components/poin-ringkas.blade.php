@php
    $akunSaya = request()->attributes->get('akun');
    $ringkas = $akunSaya?->siswa ? \App\Services\Gamifikasi::ringkas($akunSaya->siswa) : null;
@endphp

@if ($ringkas)
    <a href="{{ route('saya.poin') }}" class="nb-card hover muncul block mb-8" style="background: var(--pink)">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-bold">Poin usahaku</p>
                <p class="judul text-4xl">{{ $ringkas['total'] }}</p>
                <p class="text-sm">{{ $ringkas['lencana'] }} lencana &middot; lihat detail &rarr;</p>
            </div>
            @if ($ringkas['target'])
                <div class="flex-1 min-w-[200px]">
                    <p class="text-sm font-bold">Target kelas{{ $ringkas['target']['judul'] ? ': ' . $ringkas['target']['judul'] : '' }}</p>
                    <div class="border-2 border-black bg-white h-5" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $ringkas['target']['persen'] }}" aria-label="Kemajuan target kelas">
                        <div class="h-full" style="width: {{ $ringkas['target']['persen'] }}%; background: {{ $ringkas['target']['tercapai'] ? 'var(--hijau)' : 'var(--oranye)' }}"></div>
                    </div>
                    <p class="text-xs">{{ $ringkas['target']['persen'] }}% ({{ $ringkas['target']['capai'] }} / {{ $ringkas['target']['target'] }})</p>
                </div>
            @endif
        </div>
    </a>
@endif