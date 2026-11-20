<x-siswa-layout :judul="$item->judul" :kelas="$kelas">
    <a href="{{ route('siswa.bab', [$kelas->kode_publik, $bab]) }}" class="font-bold underline">&larr; {{ $bab->judul }}</a>

    <div class="flex items-center gap-3 mt-4 mb-6">
        <span class="ikon-tipe">{{ $item->label() }}</span>

        <div>
            <p class="text-sm">Materi {{ $nomor }} dari {{ $total }}</p>
            <h1 class="text-2xl md:text-3xl">{{ $item->judul }}</h1>
        </div>
    </div>

    <div class="muncul">
        @include('siswa._item', [
            'item' => $item,
            'kode' => $kelas->kode_publik,
            'tampilJudul' => false,
        ])
    </div>

    <div class="flex flex-wrap justify-between gap-3 mt-10">
        @if ($sebelum)
            <a
                class="nb-btn putih"
                href="{{ route('siswa.item', [$kelas->kode_publik, $sebelum]) }}"
            >&larr; {{ \Illuminate\Support\Str::limit($sebelum->judul, 28) }}</a>
        @else
            <span></span>
        @endif

        @if ($sesudah)
            <a
                class="nb-btn"
                href="{{ route('siswa.item', [$kelas->kode_publik, $sesudah]) }}"
            >{{ \Illuminate\Support\Str::limit($sesudah->judul, 28) }} &rarr;</a>
        @else
            <a class="nb-btn" href="{{ route('siswa.bab', [$kelas->kode_publik, $bab]) }}">Selesai &mdash; ke daftar materi</a>
        @endif
    </div>
</x-siswa-layout>