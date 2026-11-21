<x-polos-layout judul="Ganti Password">
    <h1 class="text-3xl mb-2">Ganti password</h1>
    <p class="mb-6">Password kamu baru saja diatur ulang oleh admin. Buat password baru yang hanya kamu yang tahu.</p>

    <form
        method="POST"
        action="{{ route('password.wajib.simpan') }}"
        class="nb-card space-y-4"
        style="background: var(--kuning)"
    >
        @csrf

        <div>
            <label class="nb-label" for="password">Password baru (minimal 8 karakter)</label>
            <input
                class="nb-input"
                type="password"
                id="password"
                name="password"
                required
                autofocus
                autocomplete="new-password"
            >
            @error('password')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="nb-label" for="password_confirmation">Ulangi password baru</label>
            <input
                class="nb-input"
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
            >
        </div>

        <button class="nb-btn w-full">Simpan password</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button class="underline text-sm">Keluar</button>
    </form>
</x-polos-layout>