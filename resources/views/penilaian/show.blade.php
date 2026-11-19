<x-guru-layout :judul="'Rekap ' . $penilaian->judul">
    <a href="{{ route('penilaian.index', $penilaian->kelas_id) }}" class="font-bold underline">
        &larr; Penilaian {{ $penilaian->kelas->nama }}
    </a>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3 mb-6">
        <div>
            <h1 class="text-3xl">{{ $penilaian->judul }}</h1>
            <p>
                {{ $penilaian->mapel?->nama ?? 'Tanpa mapel' }} &middot; {{ $penilaian->tanggal->translatedFormat('d M Y') }}
                &middot; KKM {{ $penilaian->kkm }}
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a class="nb-btn" href="{{ route('penilaian.nilai', $penilaian) }}">Lanjut menilai</a>
            <a class="nb-btn biru" href="{{ route('penilaian.ekspor', $penilaian) }}">Ekspor Excel</a>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid gap-4 grid-cols-2 md:grid-cols-5 mb-6">
        <div class="nb-card" style="background: var(--kuning)">
            <p class="text-sm">Dinilai</p>
            <p class="judul text-2xl">{{ $stat['dinilai'] }}/{{ $stat['jumlah'] }}</p>
        </div>

        <div class="nb-card" style="background: var(--biru)">
            <p class="text-sm">Rata-rata</p>
            <p class="judul text-2xl">{{ $stat['rata'] ?? '-' }}</p>
        </div>

        <div class="nb-card" style="background: var(--hijau)">
            <p class="text-sm">Tertinggi</p>
            <p class="judul text-2xl">{{ $stat['tinggi'] ?? '-' }}</p>
        </div>

        <div class="nb-card" style="background: var(--oranye)">
            <p class="text-sm">Terendah</p>
            <p class="judul text-2xl">{{ $stat['rendah'] ?? '-' }}</p>
        </div>

        <div class="nb-card" style="background: var(--pink)">
            <p class="text-sm">Tuntas</p>
            <p class="judul text-2xl">{{ $stat['tuntas'] }}/{{ $stat['dinilai'] }}</p>
        </div>
    </div>

    {{-- Distribusi --}}
    @php
        $maks = max(1, max($distribusi));
    @endphp

    <div class="nb-card mb-6">
        <h2 class="text-lg mb-3">Sebaran nilai</h2>

        <div class="space-y-2">
            @foreach ($distribusi as $label => $jumlah)
                <div class="flex items-center gap-3">
                    <span class="w-16 font-bold text-sm">{{ $label }}</span>

                    <div class="flex-1 border-2 border-black h-7 bg-white">
                        <div
                            class="h-full"
                            style="width: {{ $jumlah / $maks * 100 }}%; background: var(--kuning); border-right: {{ $jumlah ? '2px solid #111' : '0' }}"
                        ></div>
                    </div>

                    <span class="w-8 text-right font-bold">{{ $jumlah }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Tabel --}}
    <div class="nb-card nb-scroll">
        <table class="nb-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    @foreach ($kriterias as $k)
                        <th>{{ $k->nama }}</th>
                    @endforeach
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($baris as $b)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $b['siswa']->nama }}</td>

                        @foreach ($kriterias as $k)
                            <td>
                                @php
                                    $v = $b['detail'][$k->id] ?? null;
                                @endphp

                                @if ($v === null)
                                    -
                                @elseif ($k->tipe === 'ceklis')
                                    {{ $v > 0 ? 'Ya' : 'Tidak' }}
                                @else
                                    {{ rtrim(rtrim(number_format($v, 2), '0'), '.') }}
                                @endif
                            </td>
                        @endforeach

                        <td class="font-bold">{{ $b['total'] ?? '-' }}</td>

                        <td>
                            @if ($b['status'] === 'belum')
                                <span class="nb-chip">Belum dinilai</span>
                            @elseif ($b['status'] === 'sebagian')
                                <span class="nb-chip" style="background: var(--kuning)">Sebagian</span>
                            @elseif ($b['tuntas'])
                                <span class="nb-chip" style="background: var(--hijau)">Tuntas</span>
                            @else
                                <span class="nb-chip" style="background: var(--oranye)">Belum tuntas</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-guru-layout>