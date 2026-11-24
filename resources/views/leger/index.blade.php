<x-guru-layout :judul="'Leger ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>
    <h1 class="text-3xl mt-3 mb-2">Leger Nilai {{ $kelas->nama }}</h1>
    <p class="mb-6">Nilai akhir tiap siswa, digabung dari semua penilaian, tugas, dan ujian di mapel yang dipilih.</p>

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

    @if (! count($daftarMapel))
        <div class="nb-card">
            <p class="font-bold">Belum ada penilaian di kelas ini.</p>
            <p class="text-sm mt-1">Buat penilaian di menu Penilaian, atau kirim nilai dari Tugas dan Ujian, lalu balik ke sini.</p>
        </div>
    @else
        @php
            $set = $h['pengaturan'];
            $st = $h['statistik'];
        @endphp

        <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
            <div>
                <label class="nb-label" for="mapel">Mapel</label>
                <select class="nb-input" style="width: 16rem" id="mapel" name="mapel" onchange="this.form.submit()">
                    @foreach ($daftarMapel as $id => $nama)
                        <option value="{{ $id }}" @selected($id === $mapel)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            <noscript><button class="nb-btn putih">Tampilkan</button></noscript>

            <a class="nb-btn biru" href="{{ route('leger.ekspor', [$kelas, 'mapel' => $mapel]) }}">Ekspor Excel</a>
            <a class="nb-btn putih" target="_blank" href="{{ route('leger.cetak', [$kelas, 'mapel' => $mapel]) }}">Cetak</a>
        </form>

        <!-- Button Atur Rekap Per-Bab -->
         <div class="nb-card mb-4" id="kotak-mode-bab">
            <p class="text-sm">
                Nilai dikelompokkan per <b>bab</b> dengan aspek Pengetahuan dan Keterampilan?
                <a class="underline font-bold" id="atur-bab" href="{{ route('leger.struktur', [$kelas, 'mapel' => $mapel]) }}">Atur rekap per bab</a>
                (mode kategori di bawah tetap tersimpan).
            </p>
        </div>

        @if (count($belum['tugas']) || count($belum['ujian']))
            <div class="nb-card mb-4" style="background: var(--oranye)">
                <p class="font-bold">Ada nilai yang belum masuk leger</p>
                <p class="text-sm">Tugas dan ujian di bawah ini sudah ada nilainya, tapi belum dikirim ke Penilaian. Buka lalu tekan "Kirim nilai".</p>

                <ul class="text-sm mt-2 list-disc pl-5">
                    @foreach ($belum['tugas'] as $t)
                        <li>Tugas: <a class="underline font-bold" href="{{ route('tugas.hasil', $t->id) }}">{{ $t->judul }}</a></li>
                    @endforeach
                    @foreach ($belum['ujian'] as $u)
                        <li>Ujian: {{ $u->judul }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="nb-chip" style="background: var(--hijau)">Rata-rata kelas: {{ $st['rata'] ?? '-' }}</span>
            <span class="nb-chip">Tertinggi: {{ $st['tinggi'] ?? '-' }}</span>
            <span class="nb-chip">Terendah: {{ $st['rendah'] ?? '-' }}</span>
            <span class="nb-chip" style="background: var(--biru)">Tuntas: {{ $st['tuntas'] }}</span>
            <span class="nb-chip" style="background: var(--merah)">Remedial: {{ $st['remedial'] }}</span>
            @if ($st['sementara'])
                <span class="nb-chip" style="background: var(--oranye)">{{ $st['sementara'] }} siswa nilainya sementara</span>
            @endif
        </div>

        <div class="nb-scroll mb-8">
            <table class="nb-table" id="tabel-leger">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        @foreach ($h['kategoriAktif'] as $k)
                            <th>{{ $kategori[$k] }}</th>
                        @endforeach
                        <th>Nilai Rapor</th>
                        <th>Predikat</th>
                        <th>Ket.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($h['baris'] as $b)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $b['siswa']->nama }}</td>
                            @foreach ($h['kategoriAktif'] as $k)
                                <td>{{ $b['rata'][$k] ?? '-' }}</td>
                            @endforeach
                            <td class="font-bold">
                                {{ $b['rapor'] ?? '-' }}
                                @if ($b['sementara'] && $b['rapor'] !== null)
                                    <span class="nb-chip" style="background: var(--oranye)" title="Ada nilai yang masih kosong">sementara</span>
                                @endif
                            </td>
                            <td class="font-bold">{{ $b['predikat'] }}</td>
                            <td>
                                @if ($b['rapor'] === null)
                                    Belum ada nilai
                                @elseif ($b['tuntas'])
                                    Tuntas
                                @else
                                    <span class="nb-chip" style="background: var(--merah)">Remedial</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (count($h['remedial']))
            <div class="nb-card mb-8">
                <p class="font-bold">Daftar remedial (di bawah KKM {{ $set['kkm'] }})</p>
                <ol class="list-decimal pl-5 mt-2">
                    @foreach ($h['remedial'] as $b)
                        <li>{{ $b['siswa']->nama }} <span class="text-sm">(nilai {{ $b['rapor'] }}, kurang {{ $set['kkm'] - $b['rapor'] }} dari KKM)</span></li>
                    @endforeach
                </ol>
            </div>
        @endif

        <form method="POST" action="{{ route('leger.simpan', $kelas) }}" class="nb-card">
            @csrf
            <input type="hidden" name="mapel_id" value="{{ $mapel }}">

            <h2 class="text-xl font-bold mb-3">Pengaturan perhitungan</h2>

            <div class="grid gap-3 md:grid-cols-3 mb-4">
                <div>
                    <label class="nb-label" for="kkm">KKM</label>
                    <input class="nb-input" type="number" id="kkm" name="kkm" min="0" max="100" value="{{ old('kkm', $set['kkm']) }}">
                </div>
                <div class="md:col-span-2">
                    <label class="nb-label" for="kosong">Kalau nilai siswa kosong</label>
                    <select class="nb-input" id="kosong" name="kosong">
                        <option value="lewati" @selected(old('kosong', $set['kosong']) === 'lewati')>Dilewati (tidak dihitung, nilai ditandai sementara)</option>
                        <option value="nol" @selected(old('kosong', $set['kosong']) === 'nol')>Dihitung 0</option>
                    </select>
                </div>
            </div>

            <p class="nb-label mb-1">Bobot tiap kategori (angka bulat)</p>
            <div class="grid gap-3 grid-cols-2 md:grid-cols-5 mb-2">
                @foreach ($kategori as $k => $nama)
                    <div>
                        <label class="text-sm font-bold" for="bobot-{{ $k }}">{{ $nama }}</label>
                        <input class="nb-input" type="number" id="bobot-{{ $k }}" name="bobot[{{ $k }}]" min="0" max="100" value="{{ old('bobot.' . $k, $set['bobot'][$k]) }}">
                    </div>
                @endforeach
            </div>
            <p class="text-sm mb-4">Total bobot sekarang {{ array_sum($set['bobot']) }}. Tidak harus 100, hasilnya otomatis dibagi total bobot kategori yang terisi. Kategori tanpa penilaian tidak ikut dihitung.</p>

            <p class="nb-label mb-1">Kategori tiap penilaian</p>
            <div class="nb-scroll mb-4">
                <table class="nb-table">
                    <thead>
                        <tr>
                            <th>Penilaian</th>
                            <th>Asal</th>
                            <th>Tanggal</th>
                            <th>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($h['penilaians'] as $p)
                            <tr>
                                <td>{{ $p->judul }}</td>
                                <td>{{ $asal[$p->id] }}</td>
                                <td>{{ $p->tanggal?->format('d/m/Y') }}</td>
                                <td>
                                    <select class="nb-input" name="kategori[{{ $p->id }}]" aria-label="Kategori {{ $p->judul }}">
                                        @foreach ($kategori as $k => $nama)
                                            <option value="{{ $k }}" @selected(($h['kategoriMap'][$p->id] ?? null) === $k)>{{ $nama }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-sm mb-4">Tugas otomatis jadi "Tugas", ujian jadi "Ulangan Harian", penilaian biasa jadi "Praktik / Proyek". Ganti sesuai kebutuhan (mis. ujian PTS atau PAS).</p>

            <button class="nb-btn kuning">Simpan dan hitung ulang</button>
        </form>

        <p class="text-sm mt-4">Predikat: D di bawah KKM, lalu rentang KKM sampai 100 dibagi tiga sama besar untuk C, B, A. Nilai rapor dibulatkan ke bilangan bulat.</p>
    @endif
</x-guru-layout>