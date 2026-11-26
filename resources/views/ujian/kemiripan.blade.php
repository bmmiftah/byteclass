<x-guru-layout :judul="'Kemiripan '.$ujian->judul">
    <a href="{{ route('ujian.hasil', $ujian) }}" class="font-bold underline">&larr; Hasil {{ $ujian->judul }}</a>
    <h1 class="text-3xl mt-3 mb-2">Kemiripan jawaban: {{ $ujian->judul }}</h1>

    <div class="nb-card mb-4" style="background: var(--kuning)">
        <p class="font-bold">Ini petunjuk untuk ditanyakan ke siswa, bukan bukti kecurangan.</p>
        <p class="text-sm mt-1">
            Siswa yang belajar bersama, atau memahami materi dengan cara salah yang sama, juga bisa menghasilkan jawaban mirip.
            Jawaban yang sama-sama benar tidak dihitung. Hanya pengerjaan yang sudah selesai yang dibandingkan.
        </p>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label class="nb-label" for="pg">Ambang pilihan ganda</label>
            <input class="nb-input" style="width: 8rem" type="number" step="0.05" min="0.5" max="1" id="pg" name="pg" value="{{ $pg }}">
        </div>
        <div>
            <label class="nb-label" for="esai">Ambang uraian</label>
            <input class="nb-input" style="width: 8rem" type="number" step="0.05" min="0.4" max="1" id="esai" name="esai" value="{{ $esai }}">
        </div>
        <button class="nb-btn putih">Terapkan</button>
    </form>
    <p class="text-sm mb-4">
        Pilihan ganda: porsi jawaban salah yang sama dari soal yang keduanya sama-sama salah (minimal 4 soal).
        Uraian: kemiripan teks 0 sampai 1 (minimal 8 kata). Naikkan ambang kalau terlalu banyak pasangan muncul.
        Dibandingkan: {{ $hasil['jumlah'] }} siswa.
    </p>

    @forelse ($hasil['pasangan'] as $ps)
        <div class="nb-card mb-3">
            <p class="font-bold text-lg">
                {{ $ps['a']['nama'] }} &amp; {{ $ps['b']['nama'] }}
                <span class="nb-chip">{{ $ps['poin'] }} poin bukti</span>
            </p>
            <ul class="list-disc pl-5 mt-1 text-sm">
                @if ($ps['pg'])
                    <li>
                        Pilihan ganda: <b>{{ $ps['pg']['sama'] }}</b> dari {{ $ps['pg']['dua_salah'] }} soal yang sama-sama salah dijawab dengan pilihan salah yang sama
                        (soal {{ implode(', ', $ps['pg']['soal']) }}).
                    </li>
                @endif
                @if ($ps['isian'])
                    <li>
                        Isian: jawaban salah yang sama persis di soal
                        {{ collect($ps['isian'])->map(fn ($i) => $i['no'].' ("'.\Illuminate\Support\Str::limit($i['jawaban'], 40).'")')->implode(', ') }}.
                    </li>
                @endif
                @foreach ($ps['esai'] as $e)
                    <li>
                        Uraian soal {{ $e['no'] }}: teks <b>{{ $e['persen'] }}%</b> mirip.
                        <details class="mt-1">
                            <summary class="underline cursor-pointer font-bold">Lihat kedua jawaban</summary>
                            <p class="mt-1"><b>{{ $ps['a']['nama'] }}:</b></p>
                            <p class="whitespace-pre-line border-2 border-black p-2 bg-white">{{ $e['a'] }}</p>
                            <p class="mt-1"><b>{{ $ps['b']['nama'] }}:</b></p>
                            <p class="whitespace-pre-line border-2 border-black p-2 bg-white">{{ $e['b'] }}</p>
                        </details>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="nb-card" style="background: var(--hijau)">
            <p class="font-bold">Tidak ada pasangan yang melewati ambang.</p>
        </div>
    @endforelse

    <p class="text-sm mt-4">Poin bukti: 1 per pilihan salah yang sama, 2 per isian salah yang sama, 3 per uraian yang mirip. Urutan dari yang terbanyak.</p>
</x-guru-layout>
