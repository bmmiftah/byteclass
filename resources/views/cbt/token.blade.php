<x-cbt-layout judul="Token Ujian">
    <main class="max-w-md mx-auto p-4 py-10 text-center">
        <a href="{{ route('cbt.daftar') }}" class="font-bold underline block text-left mb-6">
            &larr; Kembali
        </a>

        <h1 class="text-3xl mb-2">{{ $ujian->judul }}</h1>
        <p class="mb-6">
            Durasi <b>{{ $ujian->durasi }} menit</b>. Waktu mulai berjalan setelah kamu menekan Mulai dan tidak bisa dihentikan.
        </p>

        <form
            method="POST"
            action="{{ route('cbt.mulai', $ujian) }}"
            class="nb-card space-y-4"
            style="background: var(--kuning)"
        >
            @csrf

            <label class="nb-label text-left" for="token">Token ujian (dari gurumu)</label>
            <input
                class="nb-input text-center judul"
                style="font-size: 1.75rem; letter-spacing: .15em"
                id="token"
                name="token"
                maxlength="8"
                autocomplete="off"
                autocapitalize="characters"
                autocorrect="off"
                spellcheck="false"
                required
            >

            @error('token')
                <p class="nb-error text-left">{{ $message }}</p>
            @enderror

            <button class="nb-btn w-full" style="font-size: 1.1rem">Mulai ujian</button>
        </form>
    </main>
</x-cbt-layout>