<x-guru-layout judul="Admin: Guru">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-3xl">Admin</h1>

        <div class="flex gap-2">
            <span class="nb-btn" style="cursor: default">Guru</span>
            <a class="nb-btn putih" href="{{ route('backup.index') }}">Cadangan data</a>
        </div>
    </div>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    {{-- Link undangan baru (tampil sekali) --}}
    @if (session('link_undangan'))
        <div class="nb-card mb-4" style="background: var(--kuning)" x-data="{ salin: false }">
            <p class="font-bold mb-2">Undangan dibuat. Kirim link ini ke gurunya (WhatsApp/email):</p>
            <input class="nb-input" readonly value="{{ session('link_undangan') }}" onclick="this.select()">
            <button
                type="button"
                class="nb-btn mt-3"
                @click="navigator.clipboard.writeText('{{ session('link_undangan') }}'); salin = true"
                x-text="salin ? 'Tersalin' : 'Salin link'"
            >
                Salin link
            </button>
        </div>
    @endif

    {{-- Password sementara (tampil sekali) --}}
    @if (session('sandi_sementara'))
        @php($s = session('sandi_sementara'))
        <div class="nb-card mb-4" style="background: var(--kuning)">
            <p class="font-bold">Password sementara untuk {{ $s['nama'] }} ({{ $s['email'] }}):</p>
            <p class="judul text-3xl my-2 select-all">{{ $s['sandi'] }}</p>
            <p class="text-sm">
                Catat dan kirim ke gurunya sekarang; password ini <b>tidak akan ditampilkan lagi</b>.
                Saat masuk, dia diminta langsung menggantinya.
            </p>
        </div>
    @endif

    {{-- Undang guru --}}
    <form
        method="POST"
        action="{{ route('admin.guru.undang') }}"
        class="nb-card mb-8 grid gap-4 md:grid-cols-5 items-end"
    >
        @csrf

        <div class="md:col-span-2">
            <label class="nb-label" for="email">Email (opsional)</label>
            <input
                class="nb-input"
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="kosongkan bila bebas"
            >
        </div>

        <div>
            <label class="nb-label" for="hari">Berlaku (hari)</label>
            <input
                class="nb-input"
                type="number"
                id="hari"
                name="hari"
                min="1"
                max="30"
                value="{{ old('hari', 7) }}"
                required
            >
        </div>

        <div>
            <label class="nb-label" for="peran">Peran</label>
            <select class="nb-input" id="peran" name="peran">
                <option value="guru">Guru</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <button class="nb-btn">Buat undangan</button>
    </form>

    {{-- Undangan aktif --}}
    @if ($undangans->isNotEmpty())
        <h2 class="text-xl mb-3">Undangan yang belum dipakai</h2>

        <div class="space-y-3 mb-8">
            @foreach ($undangans as $u)
                <div class="nb-card flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-bold">
                            {{ $u->email ?? 'Siapa saja yang memegang link' }}
                            <span class="nb-chip">{{ $u->peran }}</span>
                        </p>
                        <p class="text-sm">Berlaku sampai {{ $u->berlaku_sampai->translatedFormat('d M Y H:i') }}</p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.undangan.batal', $u) }}"
                        onsubmit="return confirm('Batalkan undangan ini?')"
                    >
                        @csrf
                        @method('DELETE')
                        <button class="nb-btn merah">Batalkan</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Daftar guru --}}
    <h2 class="text-xl mb-3">Semua akun ({{ $gurus->count() }})</h2>

    <div class="space-y-3">
        @foreach ($gurus as $g)
            <div class="nb-card" style="{{ $g->aktif ? '' : 'background: #eee' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-bold text-lg">
                            {{ $g->name }}
                            <span
                                class="nb-chip"
                                style="background: {{ $g->adalahAdmin() ? 'var(--pink)' : 'var(--biru)' }}"
                            >
                                {{ $g->peran }}
                            </span>
                            @unless ($g->aktif)
                                <span class="nb-chip" style="background: var(--merah)">nonaktif</span>
                            @endunless
                            @if ($g->id === auth()->id())
                                <span class="nb-chip">kamu</span>
                            @endif
                        </p>

                        <p class="text-sm">{{ $g->email }}</p>

                        <p class="text-sm mt-1">
                            {{ $g->kelas_count }} kelas &middot;
                            {{ (int) ($siswaPerGuru[$g->id] ?? 0) }} siswa &middot;
                            {{ \Illuminate\Support\Number::fileSize((int) ($pakaiPerGuru[$g->id] ?? 0), 1) }} file materi &middot;
                            terakhir masuk {{ $g->terakhir_masuk?->diffForHumans() ?? 'belum pernah' }}
                        </p>
                    </div>

                    @if ($g->id !== auth()->id())
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.guru.status', $g) }}">
                                @csrf
                                <button class="nb-btn putih">{{ $g->aktif ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.guru.sandi', $g) }}"
                                onsubmit="return confirm('Buat password sementara untuk {{ $g->name }}? Password lamanya tidak berlaku lagi.')"
                            >
                                @csrf
                                <button class="nb-btn putih">Atur ulang password</button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('admin.guru.peran', $g) }}"
                                onsubmit="return confirm('Ubah peran {{ $g->name }}?')"
                            >
                                @csrf
                                <button class="nb-btn putih">{{ $g->adalahAdmin() ? 'Jadikan guru' : 'Jadikan admin' }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-sm mt-6">
        Lewat halaman web, admin mengelola akun tetapi <b>tidak</b> bisa membuka kelas, nilai, atau materi guru lain.
        Catatan: file <b>Cadangan data</b> memuat seluruh isi database, jadi simpan dengan hati-hati.
        Akun tidak dihapus agar datanya aman; cukup dinonaktifkan. Angka file materi berupa perkiraan.
    </p>
</x-guru-layout>