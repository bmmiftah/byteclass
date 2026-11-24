<x-guru-layout :judul="'Leger per bab ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>
    <h1 class="text-3xl mt-3 mb-2">Leger per Bab {{ $kelas->nama }}</h1>
    <p class="mb-6">Nilai Pengetahuan dan Keterampilan per bab, lalu nilai rapor tiap aspek.</p>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    @if (! count($daftarMapel))
        <div class="nb-card">
            <p class="font-bold">Belum ada penilaian atau bab di kelas ini.</p>
            <p class="text-sm mt-1">Buat penilaian (atau kirim nilai dari Tugas dan Ujian), lalu susun bab di halaman struktur.</p>
        </div>
    @else
        @php $set = $h['pengaturan']; $st = $h['statistik']; $babs = $h['babs']; @endphp

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
            <a class="nb-btn kuning" id="ke-struktur" href="{{ route('leger.struktur', [$kelas, 'mapel' => $mapel]) }}">Atur bab dan slot</a>
            <a class="nb-btn biru" href="{{ route('leger.bab.ekspor', [$kelas, 'mapel' => $mapel]) }}">Ekspor Excel</a>
            <a class="nb-btn putih" target="_blank" href="{{ route('leger.bab.cetak', [$kelas, 'mapel' => $mapel]) }}">Cetak</a>
        </form>

        <form method="POST" action="{{ route('leger.mode', $kelas) }}" class="mb-4 text-sm">
            @csrf
            <input type="hidden" name="mapel_id" value="{{ $mapel }}">
            <input type="hidden" name="mode" value="kategori">
            Mode rekap: <b>per bab</b>. <button class="underline font-bold" id="ke-kategori">Kembali ke mode kategori</button> (nilai dan susunan bab tidak hilang).
        </form>

        @if (! $babs->count())
            <div class="nb-card mb-4" style="background: var(--kuning)" id="belum-bab">
                <p class="font-bold">Belum ada bab untuk mapel ini.</p>
                <p class="text-sm mt-1">Buka <a class="underline font-bold" href="{{ route('leger.struktur', [$kelas, 'mapel' => $mapel]) }}">Atur bab dan slot</a> untuk membuat bab pertama.</p>
            </div>
        @else
            @if (count($h['slotKosong']))
                <div class="nb-card mb-4" style="background: var(--oranye)" id="slot-kosong">
                    <p class="font-bold">{{ count($h['slotKosong']) }} slot belum dihubungkan ke penilaian</p>
                    <ul class="text-sm mt-2 list-disc pl-5">
                        @foreach (array_slice($h['slotKosong'], 0, 8) as $k)
                            <li>{{ $k['bab'] }} / {{ $k['aspek'] }} / {{ $k['slot'] }}</li>
                        @endforeach
                    </ul>
                    <p class="text-sm mt-1">Slot kosong dilewati dan bobot slot lain dihitung ulang; nilainya ditandai belum lengkap (*).</p>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 mb-4">
                @foreach ($aspek as $kode => $label)
                    @if ($h['aspekAktif'][$kode])
                        <span class="nb-chip" style="background: var(--hijau)">{{ $label }} rata-rata: {{ $st[$kode]['rata'] ?? '-' }}</span>
                        <span class="nb-chip" style="background: var(--biru)">{{ $label }} tuntas: {{ $st[$kode]['tuntas'] }}</span>
                        <span class="nb-chip" style="background: var(--merah)">{{ $label }} remedial: {{ $st[$kode]['remedial'] }}</span>
                    @endif
                @endforeach
                <span class="nb-chip">KKM: {{ $set['kkm'] }}</span>
                @if ($st['sementara'])
                    <span class="nb-chip" style="background: var(--oranye)">{{ $st['sementara'] }} siswa belum lengkap</span>
                @endif
            </div>

            @php $nPtsPas = ($h['pts'] ? 1 : 0) + ($h['pas'] ? 1 : 0); @endphp

            <div class="nb-scroll mb-8">
                <table class="nb-table" id="tabel-bab">
                    <thead>
                        <tr>
                            <th rowspan="3">No</th>
                            <th rowspan="3">Nama</th>
                            @foreach ($babs as $b)
                                @php
                                    $lebar = 0;
                                    foreach ($aspek as $kode => $l) {
                                        if ($b->{$kode}) {
                                            $lebar += max(1, $b->slots->where('aspek', $kode)->count()) + 1;
                                        }
                                    }
                                @endphp
                                @if ($lebar)
                                    <th colspan="{{ $lebar }}" class="bab-judul">{{ $b->nama }}</th>
                                @endif
                            @endforeach
                            @if ($h['pts'])
                                <th rowspan="3">PTS</th>
                            @endif
                            @if ($h['pas'])
                                <th rowspan="3">PAS</th>
                            @endif
                            <th colspan="{{ 2 * count(array_filter($h['aspekAktif'])) }}">Nilai Rapor</th>
                            <th rowspan="3">Ket.</th>
                        </tr>
                        <tr>
                            @foreach ($babs as $b)
                                @foreach ($aspek as $kode => $l)
                                    @if ($b->{$kode})
                                        <th colspan="{{ max(1, $b->slots->where('aspek', $kode)->count()) + 1 }}">{{ $l }}</th>
                                    @endif
                                @endforeach
                            @endforeach
                            @foreach ($aspek as $kode => $l)
                                @if ($h['aspekAktif'][$kode])
                                    <th colspan="2">{{ $l }}</th>
                                @endif
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($babs as $b)
                                @foreach ($aspek as $kode => $l)
                                    @if ($b->{$kode})
                                        @foreach ($b->slots->where('aspek', $kode) as $s)
                                            <th>
                                                {{ $s->nama }} <span class="nb-chip">{{ $s->bobot }}%</span>@if (! $s->penilaian)<span title="Belum dihubungkan ke penilaian">!</span>@endif
                                            </th>
                                        @endforeach
                                        <th>n-KD</th>
                                    @endif
                                @endforeach
                            @endforeach
                            @foreach ($aspek as $kode => $l)
                                @if ($h['aspekAktif'][$kode])
                                    <th>Nilai</th>
                                    <th>Predikat</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($h['baris'] as $r)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    {{ $r['siswa']->nama }}@if ($r['sementara'])<span class="nb-chip" style="background: var(--oranye)" title="Ada slot atau bab yang belum terisi">belum lengkap</span>@endif
                                </td>
                                @foreach ($babs as $b)
                                    @foreach ($aspek as $kode => $l)
                                        @if ($b->{$kode})
                                            @foreach ($b->slots->where('aspek', $kode) as $s)
                                                <td>{{ isset($r['slot'][$s->id]) ? rtrim(rtrim(number_format($r['slot'][$s->id], 2, '.', ''), '0'), '.') : '-' }}</td>
                                            @endforeach
                                            <td class="font-bold">{{ $r['nkd'][$b->id][$kode] ?? '-' }}</td>
                                        @endif
                                    @endforeach
                                @endforeach
                                @if ($h['pts'])
                                    <td>{{ isset($r['pts']) ? rtrim(rtrim(number_format($r['pts'], 2, '.', ''), '0'), '.') : '-' }}</td>
                                @endif
                                @if ($h['pas'])
                                    <td>{{ isset($r['pas']) ? rtrim(rtrim(number_format($r['pas'], 2, '.', ''), '0'), '.') : '-' }}</td>
                                @endif
                                @foreach ($aspek as $kode => $l)
                                    @if ($h['aspekAktif'][$kode])
                                        @php $n = $r['nilai'][$kode]; @endphp
                                        <td class="font-bold" @if ($n !== null && $n < $set['kkm']) style="background: var(--merah)" @endif>{{ $n ?? '-' }}@if ($n !== null && $r['kurang'][$kode])*@endif</td>
                                        <td>{{ $r['predikat'][$kode] }}</td>
                                    @endif
                                @endforeach
                                <td>{{ $r['sementara'] ? 'Belum lengkap' : 'Lengkap' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-sm mb-8">Tanda * = nilai dihitung dari slot atau bab yang terisi saja (yang kosong dilewati). n-KD dibulatkan sebelum dirata-ratakan antar bab.</p>
        @endif
    @endif
</x-guru-layout>