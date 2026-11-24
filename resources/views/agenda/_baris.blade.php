@php
    [$label, $warna] = \App\Services\KalenderKelas::JENIS[$a->jenis] ?? \App\Services\KalenderKelas::JENIS['kegiatan'];
    $fmt = $a->seharian ? 'l, j F Y' : 'l, j F Y \p\u\k\u\l H.i';
@endphp

<div class="nb-card muncul">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="flex-1 min-w-[200px]">
            <p class="font-bold text-lg">{{ $a->judul }}</p>
            <p class="text-sm">
                {{ $a->mulai->copy()->locale('id')->translatedFormat($fmt) }}
                @if ($a->selesai && ! $a->selesai->isSameDay($a->mulai))
                    sampai {{ $a->selesai->copy()->locale('id')->translatedFormat('l, j F Y') }}
                @endif
            </p>
            @if ($a->keterangan)
                <p class="text-sm whitespace-pre-line mt-1">{{ $a->keterangan }}</p>
            @endif
        </div>
        <span class="nb-chip" style="background: {{ $warna }}">{{ $label }}</span>
    </div>

    <div class="flex flex-wrap gap-2 mt-3">
        <a class="nb-btn putih" href="{{ route('agenda.edit', $a) }}">Ubah</a>
        <form method="POST" action="{{ route('agenda.destroy', $a) }}" onsubmit="return confirm('Hapus agenda ini?')">
            @csrf
            @method('DELETE')
            <button class="nb-btn merah">Hapus</button>
        </form>
    </div>
</div>