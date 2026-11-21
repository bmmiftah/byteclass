<x-cbt-layout judul="Masuk">
    <main class="max-w-md mx-auto p-4 py-10 text-center">
        <h1 class="text-4xl mb-2 muncul">Ujian Online</h1>
        <p class="mb-8 muncul" style="animation-delay: 80ms">
            Masukkan kode dari kartu loginmu.
        </p>

        <form
            method="POST"
            action="{{ route('cbt.proses') }}"
            class="nb-card space-y-4 muncul"
            style="background: var(--kuning); animation-delay: 160ms"
        >
            @csrf

            <label class="nb-label text-left" for="kode">Kode login</label>
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
                <p class="nb-error text-left">{{ $message }}</p>
            @enderror

            <button class="nb-btn w-full" style="font-size: 1.1rem">Masuk</button>
        </form>

        <p class="text-sm mt-6">
            Lupa atau hilang kartu? Minta gurumu mencetak ulang.
        </p>
    </main>
</x-cbt-layout>