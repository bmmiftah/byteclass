<x-siswa-layout judul="Masuk Dashboard Saya">
    <div class="max-w-md mx-auto py-6 md:py-12 text-center">
        <h1 class="text-4xl md:text-5xl mb-3 muncul">Dashboard Saya</h1>

        <p class="mb-8 muncul" style="animation-delay: 80ms">
            Masukkan kode pribadi dari kartu Kode Masuk-mu.
        </p>

        <form
            method="POST"
            action="{{ route('saya.proses') }}"
            class="nb-card space-y-4 muncul text-left"
            style="background: var(--kuning); animation-delay: 160ms"
        >
            @csrf

            <label class="nb-label" for="kode">Kode pribadi</label>

            <input
                class="nb-input text-center judul"
                style="font-size: 1.75rem; letter-spacing: .12em"
                id="kode"
                name="kode"
                value="{{ old('kode', $kode) }}"
                maxlength="12"
                autocomplete="off"
                autocapitalize="characters"
                autocorrect="off"
                spellcheck="false"
                required
                placeholder="XXXX-XXXX"
            >

            @error('kode')
                <p class="nb-error">{{ $message }}</p>
            @enderror

            <label class="flex items-center gap-3 font-bold cursor-pointer">
                <input type="checkbox" name="ingat" value="1" class="w-6 h-6">
                Ingat aku di perangkat ini
            </label>

            <p class="text-sm">Hapus centang kalau kamu memakai perangkat umum.</p>

            <button class="nb-btn w-full" style="font-size: 1.1rem">Masuk</button>
        </form>

        <p class="text-sm mt-6">
            Lupa atau hilang kodenya? Minta gurumu mengirim ulang kartu Kode Masuk.
        </p>

        <p class="text-sm mt-2">
            <a class="underline" href="{{ route('siswa.beranda') }}">Kembali ke halaman utama</a>
        </p>
    </div>
</x-siswa-layout>