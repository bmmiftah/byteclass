<x-siswa-layout judul="Masuk">
    <div class="max-w-xl mx-auto text-center py-6 md:py-12 relative">
        {{-- hiasan neo-brutalism --}}
        <svg
            class="hidden md:block absolute -left-32 top-0 muncul"
            width="110"
            height="110"
            viewBox="0 0 110 110"
            aria-hidden="true"
        >
            <rect x="8" y="8" width="84" height="84" fill="#FF6B9D" stroke="#111" stroke-width="4" />
            <rect
                x="18"
                y="18"
                width="84"
                height="84"
                fill="none"
                stroke="#111"
                stroke-width="4"
                stroke-dasharray="8 6"
            />
        </svg>

        <svg
            class="hidden md:block absolute -right-28 top-28 muncul"
            width="100"
            height="100"
            viewBox="0 0 100 100"
            aria-hidden="true"
            style="animation-delay: 120ms"
        >
            <circle cx="46" cy="46" r="38" fill="#4D96FF" stroke="#111" stroke-width="4" />
            <circle cx="56" cy="56" r="38" fill="none" stroke="#111" stroke-width="4" />
        </svg>

        <svg
            class="hidden md:block absolute -left-24 bottom-0 muncul"
            width="100"
            height="90"
            viewBox="0 0 100 90"
            aria-hidden="true"
            style="animation-delay: 240ms"
        >
            <polygon points="46,6 88,80 4,80" fill="#6BCB77" stroke="#111" stroke-width="4" />
        </svg>

        <h1 class="text-4xl md:text-5xl mb-3 muncul">Ayo Belajar</h1>
        <p class="mb-8 muncul" style="animation-delay: 80ms">
            Masukkan kode akses dari gurumu untuk membuka dashboard kelas.
        </p>

        <form
            method="GET"
            action="{{ route('siswa.beranda') }}"
            class="nb-card space-y-4 muncul"
            style="background: var(--kuning); animation-delay: 160ms"
        >
            <label class="nb-label text-left" for="kode">Kode akses</label>
            <input
                class="nb-input text-center judul"
                style="font-size: 1.75rem; letter-spacing: .1em"
                id="kode"
                name="kode"
                maxlength="12"
                autocomplete="off"
                autocapitalize="none"
                autocorrect="off"
                spellcheck="false"
                required
                value="{{ request('kode') }}"
                placeholder="contoh: k7x2p9qm"
            >

            @if ($galat)
                <p class="nb-error text-left">{{ $galat }}</p>
            @endif

            <button class="nb-btn w-full" style="font-size: 1.1rem">Buka dashboard kelas</button>
        </form>

        @if (Route::has('cbt.masuk'))
            <div class="mt-8 muncul" style="animation-delay: 240ms">
                <p class="mb-2 text-sm">Mau mengerjakan ujian?</p>
                <a class="nb-btn putih" href="{{ route('cbt.masuk') }}">Masuk ujian (CBT)</a>
            </div>
        @endif

        <p class="text-sm mt-10">
            @auth
                <a class="underline font-bold" href="{{ route('dashboard') }}">Ke dasbor guru</a>
            @else
                <a class="underline" href="{{ route('login') }}">Masuk sebagai guru</a>
            @endauth
        </p>
    </div>
</x-siswa-layout>