@php
    $r = $h['ringkas'];
    $maks = max(1, max($h['sebaran']));
@endphp

<x-guru-layout :judul="'Analisis ' . $ujian->judul">
    <a href="{{ route('ujian.hasil', $ujian) }}" class="font-bold underline">&larr; Hasil: {{ $ujian->judul }}</a>
    <h1 class="text-3xl mt-3 mb-2">Analisis: {{ $ujian->judul }}</h1>

    @if ($ujian->isRemedial())
        <div class="nb-card mb-4" style="background: var(--kuning)">
            Ini ujian <b>remedial</b> dari
            <a class="underline font-bold" href="{{ route('analisis.ujian', $asal) }}">{{ $asal->judul }}</a>.
        </div>
    @endif

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

    @if ($r['menunggu'])
        <div class="nb-card mb-4" style="background: var(--oranye)">
            {{ $r['menunggu'] }} pengerjaan masih menunggu koreksi uraian, jadi <b>belum ikut</b> dianalisis. Koreksi dulu di halaman hasil supaya angkanya lengkap.
        </div>
    @endif

    @if ($r['peserta'] === 0)
        <div class="nb-card">
            <p class="font-bold">Belum ada hasil yang bisa dianalisis.</p>
            <p class="text-sm mt-1">Analisis muncul setelah ada siswa yang menyelesaikan ujian ini (dan uraiannya sudah dikoreksi).</p>
        </div>
    @else
        <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
            <div>
                <label class="nb-label" for="kkm">KKM</label>
                <input class="nb-input" style="width: 7rem" type="number" id="kkm" name="kkm" min="0" max="100" value="{{ $kkm }}">
            </div>
            <button class="nb-btn putih">Hitung ulang</button>
            <a class="nb-btn putih" href="{{ route('analisis.ujian', [$ujian, 'unduh' => 1]) }}">Unduh Excel</a>
        </form>

        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="nb-chip">Peserta: {{ $r['peserta'] }}</span>
            <span class="nb-chip" style="background: var(--hijau)">Rata-rata: {{ $r['rata'] }}</span>
            <span class="nb-chip">Median: {{ $r['median'] }}</span>
            <span class="nb-chip">Tertinggi: {{ $r['tinggi'] }}</span>
            <span class="nb-chip">Terendah: {{ $r['rendah'] }}</span>
            <span class="nb-chip">Simpangan baku: {{ $r['sd'] }}</span>
            <span class="nb-chip" style="background: var(--biru)">Tuntas: {{ $r['tuntas'] }}</span>
            <span class="nb-chip" style="background: var(--merah)">Di bawah KKM: {{ $r['remedial'] }}</span>
            @if ($r['belum'])
                <span class="nb-chip" style="background: var(--oranye)">{{ $r['belum'] }} belum mengerjakan</span>
            @endif
        </div>

        <div class="nb-card mb-6">
            <p class="font-bold mb-2">Sebaran nilai</p>
            @foreach ($h['sebaran'] as $i => $jml)
                <div class="flex items-center gap-2 text-sm" data-bin="{{ $i }}">
                    <span style="width: 5rem">{{ $i * 10 }}{{ $i === 9 ? '-100' : '-' . ($i * 10 + 9) }}</span>
                    <div style="height: 1rem; background: var(--ink); width: {{ $jml / $maks * 60 }}%; min-width: {{ $jml ? '4px' : '0' }}"></div>
                    <span>{{ $jml }}</span>
                </div>
            @endforeach
        </div>

        <h2 class="text-xl font-bold mb-2">Analisis per soal</h2>
        @if ($r['peserta'] < \App\Services\AnalisisUjian::MIN_DAYA_PEMBEDA)
            <p class="text-sm mb-2">Peserta baru {{ $r['peserta'] }}. Daya pembeda dan pengecoh baru dihitung mulai {{ \App\Services\AnalisisUjian::MIN_DAYA_PEMBEDA }} peserta, karena di bawah itu hasilnya menyesatkan.</p>
        @endif

        <div class="nb-scroll mb-8">
            <table class="nb-table" id="tabel-soal">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Soal</th>
                        <th>% Benar</th>
                        <th>Kesukaran</th>
                        <th>Daya pembeda</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($h['soal'] as $s)
                        <tr @if ($s['perhatian']) style="background: var(--merah)" @endif>
                            <td>{{ $s['no'] }}</td>
                            <td>
                                {{ $s['teks'] }}<br>
                                <span class="text-sm">{{ ['pg' => 'Pilihan ganda', 'isian' => 'Isian', 'esai' => 'Uraian'][$s['tipe']] ?? $s['tipe'] }}, bobot {{ $s['bobot'] }}</span>
                                @if ($s['opsi'])
                                    <div class="text-sm mt-1">
                                        @foreach ($s['opsi'] as $o)
                                            <span class="nb-chip" @if ($o['kunci']) style="background: var(--hijau)" @endif>{{ $o['huruf'] }}: {{ $o['jumlah'] }} ({{ $o['persen'] }}%)@if ($o['kunci']) ✓@endif</span>
                                        @endforeach
                                        @if ($s['kosong'])
                                            <span class="nb-chip">Kosong: {{ $s['kosong'] }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="font-bold">{{ $s['persen'] }}%</td>
                            <td>{{ $s['kesukaran'] }}</td>
                            <td>{{ $s['dp'] === null ? '-' : number_format($s['dp'], 2) . ' (' . $s['dpLabel'] . ')' }}</td>
                            <td class="text-sm">
                                @forelse ($s['catatan'] as $c)
                                    <p>{{ $c }}</p>
                                @empty
                                    -
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-sm mb-8">Kesukaran: di bawah 30% benar = Sulit, di atas 70% = Mudah. Daya pembeda = proporsi benar kelompok nilai tertinggi (27%) dikurangi kelompok terendah (27%): 0,40 ke atas sangat baik, 0,30 baik, 0,20 cukup, di bawah 0,20 jelek.</p>
    @endif

    {{-- ---------- Remedial ---------- --}}
    <h2 class="text-2xl font-bold mb-3" id="remedial">Remedial</h2>

    @if ($remedials->count())
        <div class="nb-card mb-4">
            <p class="font-bold mb-2">Ujian remedial untuk {{ $asal->judul }}</p>
            <ul class="list-disc pl-5">
                @foreach ($remedials as $rm)
                    <li>
                        <a class="underline font-bold" href="{{ route('ujian.edit', $rm) }}">{{ $rm->judul }}</a>
                        <span class="nb-chip">{{ $rm->labelStatus() }}</span>
                        <span class="text-sm">{{ $rm->soals_count }} soal, {{ $rm->pengerjaans_count }} siswa mengerjakan</span>
                        <a class="underline text-sm" href="{{ route('ujian.hasil', $rm) }}">Hasil</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $ujian->isRemedial() && $r['peserta'] > 0)
        @if ($h['kandidat']->isEmpty())
            <div class="nb-card mb-6">
                <p>Tidak ada siswa di bawah KKM {{ $kkm }}. Remedial tidak diperlukan.</p>
            </div>
        @else
            <form method="POST" action="{{ route('analisis.remedial', $ujian) }}" class="nb-card mb-6">
                @csrf
                <p class="font-bold mb-1">Buat ujian remedial otomatis</p>
                <p class="text-sm mb-3">Ujian dibuat sebagai <b>draf</b> dan hanya terlihat oleh siswa yang dicentang. Soalnya sudah dipilihkan dari yang paling banyak salah; ubah sesukamu. Soal dan pilihan jawaban diacak. Kamu masih bisa mengganti soal di halaman edit sebelum ujian dibuka.</p>

                <p class="nb-label">Peserta (siswa di bawah KKM {{ $kkm }})</p>
                <div class="grid gap-1 md:grid-cols-3 mb-3">
                    @foreach ($siswaKelas as $s)
                        @php $k = $h['kandidat']->firstWhere('siswa_id', $s->id); @endphp
                        <label class="text-sm">
                            <input type="checkbox" name="siswa_ids[]" value="{{ $s->id }}" @checked($k)>
                            {{ $s->nama }}
                            @if ($k)
                                <span class="nb-chip" style="background: var(--merah)">{{ rtrim(rtrim(number_format($k->nilai, 2), '0'), '.') }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>

                <p class="nb-label">Soal untuk paket remedial</p>
                <div class="grid gap-1 mb-3">
                    @foreach ($h['soal'] as $s)
                        <label class="text-sm">
                            <input type="checkbox" name="soal_ids[]" value="{{ $s['id'] }}" @checked(in_array($s['id'], $h['rekomendasi'], true))>
                            No {{ $s['no'] }}: {{ $s['teks'] }}
                            <span class="nb-chip">salah pada {{ $h['salahKandidat'][$s['id']] ?? 0 }} dari {{ $h['kandidat']->count() }} siswa remedial</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="nb-label" for="durasi">Durasi (menit)</label>
                        <input class="nb-input" style="width: 8rem" type="number" id="durasi" name="durasi" min="1" max="300" value="{{ $ujian->durasi }}">
                    </div>
                    <button class="nb-btn kuning">Buat ujian remedial (draf)</button>
                </div>
            </form>
        @endif
    @endif

    @if (count($banding))
        <div class="nb-card mb-6" id="banding">
            <p class="font-bold mb-1">Nilai remedial dan nilai akhir</p>
            <p class="text-sm mb-3">Kalau siswa mengerjakan lebih dari satu remedial, yang dipakai nilai remedial tertinggi. Nilai asli diambil dari ujian {{ $asal->judul }}.</p>

            <form method="GET" class="flex flex-wrap items-end gap-3 mb-3">
                <input type="hidden" name="kkm" value="{{ $kkm }}">
                <div>
                    <label class="nb-label" for="aturan">Aturan nilai akhir</label>
                    <select class="nb-input" id="aturan" name="aturan" onchange="this.form.submit()">
                        @foreach ($daftarAturan as $k => $label)
                            <option value="{{ $k }}" @selected($aturan === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button class="nb-btn putih">Lihat</button></noscript>
            </form>

            <div class="nb-scroll mb-3">
                <table class="nb-table" id="tabel-banding">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Nilai asli</th>
                            <th>Nilai remedial</th>
                            <th>Nilai akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($banding as $b)
                            <tr>
                                <td>{{ $b['siswa']->nama }}</td>
                                <td>{{ rtrim(rtrim(number_format($b['asli'], 2), '0'), '.') }}</td>
                                <td>{{ rtrim(rtrim(number_format($b['remedial'], 2), '0'), '.') }}</td>
                                <td class="font-bold">{{ rtrim(rtrim(number_format($b['akhir'], 2), '0'), '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form
                method="POST"
                action="{{ route('analisis.terapkan', $ujian) }}"
                onsubmit="return confirm('Terapkan nilai akhir ini ke Penilaian? Nilai siswa di atas akan diganti. Bisa diulang kapan saja.')"
            >
                @csrf
                <input type="hidden" name="aturan" value="{{ $aturan }}">
                <input type="hidden" name="kkm" value="{{ $kkm }}">
                <button class="nb-btn kuning">Terapkan ke Penilaian</button>
            </form>

            <p class="text-sm mt-2">Kalau lu kirim ulang nilai ujian asli ke Penilaian, nilai remedial tertimpa. Tinggal tekan Terapkan lagi.</p>
        </div>
    @endif
</x-guru-layout>