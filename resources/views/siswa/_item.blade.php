@php
    $urlInline = route('siswa.berkas', [$kode, $item]);
    $urlUnduh = route('siswa.berkas', [$kode, $item, 'unduh' => 1]);
@endphp

<div class="flex items-center gap-3 mb-3">
    <span class="ikon-tipe">{{ $item->label() }}</span>
    <h2 class="text-xl md:text-2xl">{{ $item->judul }}</h2>
</div>

@if ($item->tipe === 'file')
    @if ($item->jenis_berkas === 'pdf')
        <iframe class="pdf-bingkai" src="{{ $urlInline }}" title="{{ $item->judul }}"></iframe>

        <div class="flex flex-wrap gap-3 mt-3">
            <a class="nb-btn" href="{{ $urlInline }}" target="_blank">Buka layar penuh</a>
            <a class="nb-btn putih" href="{{ $urlUnduh }}">Unduh PDF</a>
        </div>

        <p class="text-xs mt-2">Jika PDF tidak tampil di HP, gunakan tombol "Buka layar penuh".</p>
    @elseif ($item->jenis_berkas === 'gambar')
        <img class="gambar-materi" src="{{ $urlInline }}" alt="{{ $item->judul }}" loading="lazy">

        <div class="mt-3">
            <a class="nb-btn putih" href="{{ $urlUnduh }}">Unduh gambar</a>
        </div>
    @else
        <div class="nb-card flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-bold">{{ $item->nama_asli }}</p>
                <p class="text-sm">{{ \Illuminate\Support\Number::fileSize($item->ukuran ?? 0, 1) }}</p>
            </div>

            <a class="nb-btn" href="{{ $urlUnduh }}">Unduh</a>
        </div>
    @endif
@elseif ($item->tipe === 'video' && $item->embed)
    <div class="video-bingkai">
        <iframe
            src="{{ $item->embed }}"
            title="{{ $item->judul }}"
            loading="lazy"
            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
            allowfullscreen
        ></iframe>
    </div>

    <div class="mt-3">
        <a class="nb-btn putih" href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">Buka di tab baru</a>
    </div>
@elseif ($item->url)
    <a class="nb-btn" href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">Buka tautan</a>
    <p class="text-xs mt-2 break-all">{{ $item->url }}</p>
@elseif ($item->tipe === 'teks')
    <div class="nb-card teks-materi">
        {!! \Illuminate\Support\Str::markdown($item->isi, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
    </div>
@endif