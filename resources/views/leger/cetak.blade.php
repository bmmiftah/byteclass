<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Leger {{ $kelas->nama }} - {{ $namaMapel }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 16px;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 4px;
        }

        p {
            margin: 2px 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        td.n, th.n {
            text-align: center;
        }

        .remedial {
            font-weight: bold;
        }

        .tombol {
            margin-bottom: 10px;
        }

        @media print {
            .tombol {
                display: none;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="tombol">
        <button onclick="window.print()">Cetak</button>
    </div>

    <h1>Leger Nilai Akhir</h1>
    <p>
        Kelas: <b>{{ $kelas->nama }}</b>
        @if ($kelas->tahun_ajaran)
            &nbsp; Tahun ajaran: {{ $kelas->tahun_ajaran }}
        @endif
        @if ($kelas->semester)
            &nbsp; Semester: {{ $kelas->semester }}
        @endif
    </p>
    <p>Mapel: <b>{{ $namaMapel }}</b> &nbsp; KKM: <b>{{ $h['pengaturan']['kkm'] }}</b></p>
    <p>
        Bobot:
        @foreach ($h['kategoriAktif'] as $k)
            {{ $kategori[$k] }} {{ $h['pengaturan']['bobot'][$k] }}@if (! $loop->last), @endif
        @endforeach
    </p>

    <table>
        <thead>
            <tr>
                <th class="n">No</th>
                <th>Nama</th>
                @foreach ($h['kategoriAktif'] as $k)
                    <th class="n">{{ $kategori[$k] }}</th>
                @endforeach
                <th class="n">Nilai Rapor</th>
                <th class="n">Predikat</th>
                <th>Ket.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($h['baris'] as $b)
                <tr>
                    <td class="n">{{ $loop->iteration }}</td>
                    <td>{{ $b['siswa']->nama }}</td>
                    @foreach ($h['kategoriAktif'] as $k)
                        <td class="n">{{ $b['rata'][$k] ?? '-' }}</td>
                    @endforeach
                    <td class="n"><b>{{ $b['rapor'] ?? '-' }}</b></td>
                    <td class="n">{{ $b['predikat'] }}</td>
                    <td class="{{ $b['rapor'] !== null && ! $b['tuntas'] ? 'remedial' : '' }}">
                        {{ $b['rapor'] === null ? 'Belum ada nilai' : ($b['tuntas'] ? 'Tuntas' : 'Remedial') }}{{ $b['sementara'] && $b['rapor'] !== null ? ' (sementara)' : '' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:10px">Rata-rata kelas: {{ $h['statistik']['rata'] ?? '-' }} &nbsp; Tuntas: {{ $h['statistik']['tuntas'] }} &nbsp; Remedial: {{ $h['statistik']['remedial'] }}</p>
    <p style="margin-top:24px">Guru mapel: {{ $guru }}</p>
</body>
</html>