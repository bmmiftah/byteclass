<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Leger per bab {{ $kelas->nama }} - {{ $namaMapel }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; margin: 16px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p { margin: 2px 0; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 3px 5px; text-align: center; }
        td.nama { text-align: left; }
        th { background: #eee; }
        .remedial { font-weight: bold; }
        .tombol { margin-bottom: 10px; }
        @media print { .tombol { display: none; } body { margin: 0; } @page { size: landscape; } }
    </style>
</head>
<body>
    @php $babs = $h['babs']; $set = $h['pengaturan']; @endphp

    <div class="tombol"><button onclick="window.print()">Cetak</button></div>

    <h1>Leger Nilai per Bab</h1>
    <p>Kelas: <b>{{ $kelas->nama }}</b> &nbsp; Mapel: <b>{{ $namaMapel }}</b> &nbsp; KKM: <b>{{ $set['kkm'] }}</b></p>

    @if ($h['pts'] || $h['pas'])
        <p>Pengetahuan = rata-rata n-KD {{ $set['bobot_nkd'] }}@if ($h['pts']), PTS {{ $set['bobot_pts'] }}@endif @if ($h['pas']), PAS {{ $set['bobot_pas'] }}@endif</p>
    @endif

    <table>
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
                        <th colspan="{{ $lebar }}">{{ $b->nama }}</th>
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
                                <th>{{ $s->nama }} ({{ $s->bobot }}%)</th>
                            @endforeach
                            <th>n-KD</th>
                        @endif
                    @endforeach
                @endforeach
                @foreach ($aspek as $kode => $l)
                    @if ($h['aspekAktif'][$kode])
                        <th>Nilai</th>
                        <th>Pred.</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($h['baris'] as $r)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="nama">{{ $r['siswa']->nama }}</td>
                    @foreach ($babs as $b)
                        @foreach ($aspek as $kode => $l)
                            @if ($b->{$kode})
                                @foreach ($b->slots->where('aspek', $kode) as $s)
                                    <td>{{ isset($r['slot'][$s->id]) ? rtrim(rtrim(number_format($r['slot'][$s->id], 2, '.', ''), '0'), '.') : '-' }}</td>
                                @endforeach
                                <td><b>{{ $r['nkd'][$b->id][$kode] ?? '-' }}</b></td>
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
                            <td class="{{ $n !== null && $n < $set['kkm'] ? 'remedial' : '' }}"><b>{{ $n ?? '-' }}</b>@if ($n !== null && $r['kurang'][$kode])*@endif</td>
                            <td>{{ $r['predikat'][$kode] }}</td>
                        @endif
                    @endforeach
                    <td>{{ $r['sementara'] ? 'Belum lengkap' : 'Lengkap' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:8px">* nilai dihitung dari slot atau bab yang terisi saja.</p>
    <p style="margin-top:24px">Guru mapel: {{ $guru }}</p>
</body>
</html>