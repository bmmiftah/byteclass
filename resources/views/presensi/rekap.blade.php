<x-guru-layout :judul="'Rekap Presensi ' . $kelas->nama">
    <a href="{{ route('presensi.index', $kelas) }}" class="font-bold underline">&larr; Presensi {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">Rekap Presensi {{ $kelas->nama }}</h1>

    <form method="GET" action="{{ route('presensi.rekap', $kelas) }}" class="flex flex-wrap items-end gap-3 mb-6">
        <div>
            <label class="nb-label" for="bulan">Bulan</label>
            <input class="nb-input" type="month" id="bulan" name="bulan" value="{{ $bulan }}">
        </div>

        <button class="nb-btn biru">Tampilkan</button>

        <a
            class="nb-btn putih"
            href="{{ route('presensi.rekap', [$kelas, 'bulan' => $bulan, 'unduh' => 1]) }}"
        >Unduh Excel</a>
    </form>

    <p class="mb-3">Hari tercatat bulan ini: <b>{{ $jumlahHari }}</b></p>

    <div class="nb-scroll">
        <table class="nb-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>H</th>
                    <th>S</th>
                    <th>I</th>
                    <th>A</th>
                    <th>% Hadir</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        @foreach ($b as $sel)
                            <td>{{ $sel }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">Belum ada siswa.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-guru-layout>