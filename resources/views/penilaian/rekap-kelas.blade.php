<x-guru-layout :judul="'Rekap ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3 mb-6">
        <h1 class="text-3xl">Rekap Nilai {{ $kelas->nama }}</h1>
        <a class="nb-btn biru" href="{{ route('penilaian.eksporKelas', $kelas) }}">Ekspor Excel</a>
    </div>

    <div class="nb-card nb-scroll">
        <table class="nb-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>

                    @foreach ($penilaians as $p)
                        <th>
                            {{ $p->judul }}<br>
                            <span class="text-xs font-normal">{{ $p->tanggal->translatedFormat('d M') }}</span>
                        </th>
                    @endforeach

                    <th>Rata-rata</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($baris as $b)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $b['siswa']->nama }}</td>

                        @foreach ($penilaians as $p)
                            <td>{{ $b['nilai'][$p->id] ?? '-' }}</td>
                        @endforeach

                        <td class="font-bold">{{ $b['rata'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 3 + $penilaians->count() }}">Belum ada siswa.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-guru-layout>