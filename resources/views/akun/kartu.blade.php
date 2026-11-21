<x-guru-layout :judul="'Kartu Login '.$kelas->nama">
    <div class="no-print flex flex-wrap items-center gap-3 mb-6">
        <a href="{{ route('akun.index', $kelas) }}" class="font-bold underline">
            &larr; Kode siswa
        </a>

        <button type="button" class="nb-btn" onclick="window.print()">
            Cetak
        </button>

        <span class="text-sm">
            Tips cetak: ukuran A4, skala 100%, centang "Background graphics".
        </span>
    </div>

    <div
        class="grid grid-cols-1 sm:grid-cols-2 gap-3"
        x-data
        x-init="$nextTick(() => $el.querySelectorAll('[data-qr]').forEach(c => QRCode.toCanvas(c, c.dataset.qr, { width: 96, margin: 1 })))"
    >
        @foreach ($siswas as $s)
            <div class="kartu nb-card flex items-center gap-4">
                <canvas
                    data-qr="{{ route('cbt.masuk', ['kode' => $s->akun->kode]) }}"
                    width="96"
                    height="96"
                ></canvas>

                <div class="min-w-0">
                    <p class="font-bold truncate">{{ $s->nama }}</p>
                    <p class="text-sm">{{ $kelas->nama }}</p>
                    <p class="text-xs mt-2">Kode login</p>
                    <p class="judul text-2xl">{{ $s->akun->kode_tampil }}</p>
                    <p class="text-xs">{{ url('/cbt') }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-guru-layout>