<x-guru-layout :judul="'Kode Masuk ' . $kelas->nama">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&family=Space+Grotesk:wght@700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">

    @vite('resources/js/kartu.js')

    <div data-kartu-halaman>
        <a href="{{ route('akun.index', $kelas) }}" class="font-bold underline">&larr; Kode siswa</a>

        <h1 class="text-3xl mt-3 mb-2">Kode Masuk {{ $kelas->nama }}</h1>
        <p class="mb-5">Satu gambar per siswa, siap dikirim lewat WhatsApp atau disimpan di galeri HP.</p>

        @if (session('ok'))
            <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
        @endif

        @if ($tanpaKode > 0)
            <div class="nb-card mb-5 flex flex-wrap items-center gap-3" style="background: var(--kuning)">
                <p class="font-bold">{{ $tanpaKode }} siswa belum punya kode.</p>
                <form method="POST" action="{{ route('akun.buat', $kelas) }}">
                    @csrf
                    <button class="nb-btn putih">Buat kode untuk mereka</button>
                </form>
            </div>
        @endif

        @if ($kartu->isEmpty())
            <div class="nb-card">
                <p class="font-bold">Belum ada kartu.</p>
                <p>Buat kode siswa dulu lewat tombol di atas atau di halaman Kode Siswa.</p>
            </div>
        @else
            <div class="flex flex-wrap items-center gap-3 mb-3">
                <button type="button" class="nb-btn" data-zip>Unduh semua ({{ $kartu->count() }}) sebagai ZIP</button>
                <span class="text-sm" data-status role="status"></span>
            </div>

            <p class="text-sm mb-6">
                Ganti kode kelas atau kode siswa? Unduh ulang kartunya, karena gambar lama tidak ikut berubah.
                Kartu bisa dicetak dari gambar yang sama lewat aplikasi apa pun.
            </p>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($kartu as $k)
                    <div class="space-y-2">
                        <canvas
                            data-kartu="{{ json_encode($k) }}"
                            class="w-full block border-[3px] border-[#111]"
                            aria-label="Kartu {{ $k['nama'] }}"
                        ></canvas>
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold truncate">{{ $k['nama'] }}</span>
                            <button type="button" class="nb-btn putih" data-unduh>Unduh PNG</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-guru-layout>