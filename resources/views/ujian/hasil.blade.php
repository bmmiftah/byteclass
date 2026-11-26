<x-guru-layout :judul="'Hasil '.$ujian->judul">
    <a href="{{ route('ujian.index', $ujian->kelas_id) }}" class="font-bold underline">&larr; Ujian {{ $ujian->kelas->nama }}</a>
    <h1 class="text-3xl mt-3 mb-6">Hasil: {{ $ujian->judul }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="nb-chip" style="background: var(--hijau)">Rata-rata: {{ $rata ?? '-' }}</span>
        <span class="nb-chip">Tertinggi: {{ $tinggi ?? '-' }}</span>
        <span class="nb-chip">Terendah: {{ $rendah ?? '-' }}</span>
        @if ($perlu) <span class="nb-chip" style="background: var(--oranye)">{{ $perlu }} perlu koreksi uraian</span> @endif
        @if ($berjalan) <span class="nb-chip" style="background: var(--biru)">{{ $berjalan }} masih mengerjakan</span> @endif
    </div>

    <div class="flex flex-wrap gap-3 mb-6">
        <a class="nb-btn putih" href="{{ route('ujian.hasil', [$ujian, 'unduh' => 1]) }}">Unduh Excel</a>
        <form method="POST" action="{{ route('ujian.kirimNilai', $ujian) }}"
              onsubmit="return confirm('Kirim nilai ke modul Penilaian? Jika sudah pernah dikirim, nilainya diperbarui.')">
            @csrf
            <button class="nb-btn kuning">Kirim nilai ke Penilaian</button>
        </form>
        <a class="nb-btn putih" href="{{ route('analisis.ujian', $ujian) }}">Analisis soal dan remedial</a>
        <a class="nb-btn putih" href="{{ route('ujian.kemiripan', $ujian) }}">Cek kemiripan jawaban</a>
        @if ($ujian->penilaian_id)
            <a class="nb-btn biru" href="{{ route('penilaian.show', $ujian->penilaian_id) }}">Lihat di Penilaian</a>
        @endif
    </div>

    <div class="nb-scroll">
        <table class="nb-table">
            <thead><tr><th>No</th><th>Nama</th><th>Status</th><th>Benar (otomatis)</th><th>Keluar</th><th>Nilai</th><th></th></tr></thead>
            <tbody>
                @foreach ($baris as $b)
                    @php $p = $b['p']; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $b['nama'] }}</td>
                        <td>{{ $b['status'] }}@if ($p?->perlu_koreksi) <span class="nb-chip" style="background: var(--oranye)">Koreksi</span>@endif</td>
                        <td>{{ $p ? $p->benar_count : '-' }}</td>
                        <td>
                            @if ($p)
                                <span @class(['font-bold', 'text-red-700' => $p->kena_batas || $p->pelanggaran > 2])>{{ $p->pelanggaran }}</span>
                                @if ($p->kena_batas)
                                    <span class="nb-chip" style="background: var(--merah)">Kena batas</span>
                                @endif
                                @if ($p->terkunci_pada)
                                    <span class="nb-chip" style="background: var(--merah)">Terkunci</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="font-bold">{{ $p?->nilai ?? '-' }}</td>
                        <td class="whitespace-nowrap">
                            @if ($p && $p->status === 'selesai')
                                <a class="underline font-bold" href="{{ route('ujian.koreksi', [$ujian, $p]) }}">Lihat / Koreksi</a>
                            @endif
                            @if ($p)
                                <a class="underline font-bold ml-2" href="{{ route('ujian.log', [$ujian, $p]) }}">Log</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-sm mt-3">Dari {{ $total }} soal. Nilai = jumlah skor / jumlah bobot x 100.</p>
</x-guru-layout>
