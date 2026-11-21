<x-cbt-layout judul="Selesai">
    <main class="max-w-md mx-auto p-4 py-10 text-center">
        <h1 class="text-4xl mb-4 muncul">Jawabanmu sudah terkirim</h1>
        <p class="mb-6 muncul" style="animation-delay: 80ms">{{ $ujian->judul }}</p>

        @if ($ujian->tampil_nilai)
            @if ($p->perlu_koreksi)
                <div class="nb-card" style="background: var(--kuning)">
                    <p class="font-bold">Nilai sementara</p>
                    <p class="judul text-5xl my-2">
                        {{ rtrim(rtrim(number_format($p->nilai, 2), '0'), '.') }}
                    </p>
                    <p class="text-sm">
                        Soal uraianmu masih diperiksa guru; nilai akhir bisa berubah.
                    </p>
                </div>
            @else
                <div class="nb-card" style="background: var(--hijau)">
                    <p class="font-bold">Nilaimu</p>
                    <p class="judul text-5xl my-2">
                        {{ rtrim(rtrim(number_format($p->nilai, 2), '0'), '.') }}
                    </p>
                </div>
            @endif
        @else
            <div class="nb-card" style="background: var(--hijau)">
                <p>Nilai akan diumumkan oleh gurumu.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('cbt.keluar') }}" class="mt-8">
            @csrf
            <button class="nb-btn putih">Keluar</button>
        </form>
    </main>
</x-cbt-layout>