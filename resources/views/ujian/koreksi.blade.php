<x-guru-layout :judul="'Koreksi '.$pengerjaan->siswa->nama">
    <a href="{{ route('ujian.hasil', $ujian) }}" class="font-bold underline">
        &larr; Hasil
    </a>

    <h1 class="text-3xl mt-3 mb-1">{{ $pengerjaan->siswa->nama }}</h1>
    <p class="mb-6">
        Nilai: <b>{{ $pengerjaan->nilai }}</b> &middot; Keluar halaman: {{ $pengerjaan->pelanggaran }}x
    </p>

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('ujian.simpanKoreksi', [$ujian, $pengerjaan]) }}" class="space-y-4">
        @csrf

        @foreach ($soals as $s)
            @php
                $j = $jawabans[$s->id] ?? null;
                $bobot = (int) $s->pivot->bobot;
            @endphp

            <div class="nb-card">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="nb-chip">{{ $loop->iteration }}</span>
                    <span class="nb-chip" style="background: var(--kuning)">{{ $s->label() }}</span>
                    <span class="nb-chip">Bobot {{ $bobot }}</span>

                    @if ($s->tipe !== 'esai')
                        <span class="nb-chip" style="background: {{ $j?->benar ? 'var(--hijau)' : 'var(--merah)' }}">
                            {{ $j?->benar ? 'Benar' : 'Salah' }}
                        </span>
                    @endif
                </div>

                <p class="whitespace-pre-line">{{ $s->teks }}</p>

                <p class="mt-3 text-sm font-bold">Jawaban siswa:</p>
                <p class="whitespace-pre-line border-2 border-black p-2 bg-white">
                    @if ($s->tipe === 'pg' && $j?->jawaban)
                        {{ $j->jawaban }}. {{ $s->opsi[$j->jawaban] ?? '' }}
                    @else
                        {{ $j?->jawaban ?: '(kosong)' }}
                    @endif
                </p>

                @if ($s->tipe === 'esai')
                    @if ($s->kunci)
                        <p class="mt-2 text-sm"><b>Pedoman:</b> {{ $s->kunci }}</p>
                    @endif

                    <div class="mt-3 w-40">
                        <label class="nb-label" for="skor_{{ $s->id }}">Skor (0-{{ $bobot }})</label>
                        <input
                            class="nb-input"
                            type="number"
                            step="0.5"
                            min="0"
                            max="{{ $bobot }}"
                            id="skor_{{ $s->id }}"
                            name="skor[{{ $s->id }}]"
                            value="{{ old('skor.'.$s->id, $j?->skor) }}"
                        >
                    </div>
                @else
                    <p class="mt-2 text-sm">
                        <b>Kunci:</b>
                        {{
                            $s->tipe === 'pg'
                                ? $s->kunci.'. '.($s->opsi[$s->kunci] ?? '')
                                : str_replace('|', ' / ', $s->kunci)
                        }}
                    </p>
                @endif
            </div>
        @endforeach

        <button class="nb-btn" style="font-size: 1.1rem">Simpan koreksi</button>
    </form>
</x-guru-layout>