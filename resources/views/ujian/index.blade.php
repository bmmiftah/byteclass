<x-guru-layout :judul="'Ujian '.$kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">
        &larr; {{ $kelas->nama }}
    </a>

    <h1 class="text-3xl mt-3 mb-6">Ujian CBT {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">
            {{ session('ok') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('ujian.store', $kelas) }}"
        class="nb-card mb-8 grid gap-4 md:grid-cols-6 items-end"
    >
        @csrf

        <div class="md:col-span-3">
            <label class="nb-label" for="judul">Judul ujian</label>
            <input
                class="nb-input"
                id="judul"
                name="judul"
                value="{{ old('judul') }}"
                placeholder="mis. Kuis Bab 3"
                required
            >
        </div>

        <div>
            <label class="nb-label" for="durasi">Durasi (menit)</label>
            <input
                class="nb-input"
                type="number"
                id="durasi"
                name="durasi"
                min="1"
                max="300"
                value="{{ old('durasi', 30) }}"
                required
            >
        </div>

        <div>
            <label class="nb-label" for="mapel_id">Mapel</label>
            <select class="nb-input" id="mapel_id" name="mapel_id">
                <option value="">-</option>
                @foreach ($mapels as $m)
                    <option value="{{ $m->id }}" @selected(old('mapel_id') == $m->id)>
                        {{ $m->nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="nb-btn">+ Ujian</button>
    </form>

    <div class="space-y-3">
        @forelse ($ujians as $u)
            @php
                $statusLabel = $u->labelStatus();
                $warna = match ($statusLabel) {
                    'Draft' => '#fff',
                    'Dijadwalkan' => 'var(--kuning)',
                    'Dibuka' => 'var(--hijau)',
                    'Ditutup' => 'var(--merah)',
                    default => '#fff',
                };
            @endphp

            <div class="nb-card flex flex-wrap items-center gap-3 justify-between muncul">
                <div class="flex-1 min-w-[200px]">
                    <p class="font-bold text-lg">{{ $u->judul }}</p>
                    <p class="text-sm">
                        {{ $u->soals_count }} soal &middot; {{ $u->durasi }} menit &middot; {{ $u->pengerjaans_count }} siswa mulai
                    </p>
                </div>

                <span class="nb-chip" style="background: {{ $warna }}">{{ $statusLabel }}</span>

                <div class="flex flex-wrap items-center gap-2">
                    <a class="nb-btn biru" href="{{ route('ujian.edit', $u) }}">Atur</a>
                    <a class="nb-btn kuning" href="{{ route('ujian.pantau', $u) }}">Pantau</a>
                    <a class="nb-btn putih" href="{{ route('ujian.hasil', $u) }}">Hasil</a>
                </div>
            </div>
        @empty
            <div class="nb-card">
                <p class="font-bold">Belum ada ujian.</p>
                <p>Buat lewat formulir di atas.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>