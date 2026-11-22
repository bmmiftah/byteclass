<x-siswa-layout judul="Tugas Saya" :kelas="$kelas">
    <a href="{{ route('saya.beranda') }}" class="font-bold underline">&larr; Dashboard saya</a>

    <h1 class="text-3xl mt-3 mb-6">Tugas saya</h1>

    @php
        $baris = function ($t) use ($punya) {
            return [$t, $t->statusUntuk($punya->get($t->id))];
        };
    @endphp

    <h2 class="text-xl mb-2">Perlu dikerjakan ({{ $perlu->count() }})</h2>
    <div class="space-y-3 mb-8">
        @forelse ($perlu as $t)
            @php [$t, $st] = $baris($t); @endphp
            <a href="{{ route('saya.tugas.show', $t->id) }}" class="nb-card hover block muncul">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-bold text-lg">{{ $t->judul }}</p>
                    <span class="nb-chip" style="background: {{ $st[2] }}">{{ $st[1] }}</span>
                </div>
                <p class="text-sm mt-1">Tenggat {{ $t->tenggatTampil() }} ({{ $t->sisaWaktu() }})</p>
            </a>
        @empty
            <div class="nb-card">
                <p class="font-bold">Tidak ada tugas yang menunggu. Mantap!</p>
            </div>
        @endforelse
    </div>

    @if ($selesai->isNotEmpty())
        <h2 class="text-xl mb-2">Sudah dikumpulkan ({{ $selesai->count() }})</h2>
        <div class="space-y-3 mb-8">
            @foreach ($selesai as $t)
                @php [$t, $st] = $baris($t); @endphp
                <a href="{{ route('saya.tugas.show', $t->id) }}" class="nb-card hover block">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-bold">{{ $t->judul }}</p>
                        <span class="nb-chip" style="background: {{ $st[2] }}">{{ $st[1] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($lewat->isNotEmpty())
        <h2 class="text-xl mb-2">Sudah ditutup ({{ $lewat->count() }})</h2>
        <div class="space-y-3 mb-8">
            @foreach ($lewat as $t)
                @php [$t, $st] = $baris($t); @endphp
                <a href="{{ route('saya.tugas.show', $t->id) }}" class="nb-card hover block">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-bold">{{ $t->judul }}</p>
                        <span class="nb-chip" style="background: {{ $st[2] }}">{{ $st[1] }}</span>
                    </div>
                    <p class="text-sm mt-1">Tenggat {{ $t->tenggatTampil() }}</p>
                </a>
            @endforeach
        </div>
    @endif
</x-siswa-layout>