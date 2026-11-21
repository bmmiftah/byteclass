<x-polos-layout judul="Daftar Guru">
    <h1 class="text-3xl mb-2">Selamat datang</h1>
    <p class="mb-6">Kamu diundang untuk memakai Asisten Guru. Isi data di bawah untuk membuat akun.</p>

    <form
        method="POST"
        action="{{ route('undangan.daftar', $undangan->token) }}"
        class="nb-card space-y-4"
        style="background: var(--kuning)"
    >
        @csrf

        <div>
            <label class="nb-label" for="name">Nama</label>
            <input
                class="nb-input"
                id="name"
                name="name"
                value="{{ old('name') }}"
                required
                autofocus
                autocomplete="name"
            >
            @error('name')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="nb-label" for="email">Email</label>
            <input
                class="nb-input"
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $undangan->email) }}"
                required
                autocomplete="username"
                @readonly($undangan->email)
            >
            @if ($undangan->email)
                <p class="text-xs mt-1">Undangan ini khusus untuk email tersebut.</p>
            @endif
            @error('email')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="nb-label" for="password">Password (minimal 8 karakter)</label>
            <input
                class="nb-input"
                type="password"
                id="password"
                name="password"
                required
                autocomplete="new-password"
            >
            @error('password')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="nb-label" for="password_confirmation">Ulangi password</label>
            <input
                class="nb-input"
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
            >
        </div>

        <button class="nb-btn w-full">Buat akun</button>
    </form>
</x-polos-layout>