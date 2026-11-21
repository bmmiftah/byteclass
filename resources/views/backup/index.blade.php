<x-guru-layout judul="Backup">
    <h1 class="text-3xl mb-6">Backup</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">
            {{ session('ok') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('backup.buat') }}"
        class="nb-card mb-8 space-y-3"
        style="background: var(--kuning)"
    >
        @csrf
        <h2 class="text-lg">Buat cadangan sekarang</h2>

        <label class="flex items-center gap-2 font-bold">
            <input type="checkbox" name="materi" value="1" class="w-5 h-5">
            Sertakan file materi (bisa besar dan lama)
        </label>

        <button class="nb-btn">Buat cadangan</button>
    </form>

    <div class="nb-card mb-8" style="background: var(--biru)">
        <p class="font-bold">Penting</p>
        <p>
            Cadangan di sini tersimpan di server yang sama. <b>Unduh secara rutin ke laptop</b> (minimal sebulan sekali)
            dan simpan di tempat lain, mis. flashdisk atau drive pribadi. File cadangan berisi data siswa; jangan dibagikan.
        </p>
    </div>

    <div class="space-y-3">
        @forelse ($daftar as $f)
            <div class="nb-card flex flex-wrap items-center gap-3 justify-between">
                <div>
                    <p class="font-bold break-all">{{ $f['nama'] }}</p>
                    <p class="text-sm">
                        {{ $f['waktu']->translatedFormat('d M Y H:i') }}
                        &middot;
                        {{ \Illuminate\Support\Number::fileSize($f['ukuran'], 1) }}
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a class="nb-btn biru" href="{{ route('backup.unduh', $f['nama']) }}">Unduh</a>
                    <form
                        method="POST"
                        action="{{ route('backup.hapus', $f['nama']) }}"
                        onsubmit="return confirm('Hapus cadangan ini?')"
                    >
                        @csrf
                        @method('DELETE')
                        <button class="font-bold underline">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="nb-card">
                <p>Belum ada cadangan.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>