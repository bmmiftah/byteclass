<x-guru-layout :judul="'Log '.$pengerjaan->siswa->nama">
    <a href="{{ route('ujian.hasil', $ujian) }}" class="font-bold underline">&larr; Hasil {{ $ujian->judul }}</a>
    <h1 class="text-3xl mt-3 mb-1">Log kejadian: {{ $pengerjaan->siswa->nama }}</h1>
    <p class="mb-4">
        Mulai {{ $pengerjaan->mulai_pada->format('H.i.s') }}
        @if ($pengerjaan->selesai_pada) &middot; selesai {{ $pengerjaan->selesai_pada->format('H.i.s') }} @endif
        &middot; Pelanggaran dihitung: <b>{{ $pengerjaan->pelanggaran }}</b>
        @if ($ujian->punyaBatas()) dari batas {{ $ujian->batas_pelanggaran }} @endif
    </p>

    <div class="flex flex-wrap gap-2 mb-4">
        @forelse ($ringkasan as $jenis => $jumlah)
            <span class="nb-chip">{{ \App\Services\Anticurang::label($jenis) }}: <b>{{ $jumlah }}</b></span>
        @empty
            <span class="nb-chip" style="background: var(--hijau)">Tidak ada kejadian tercatat</span>
        @endforelse
    </div>

    @if ($pengerjaan->terkunci_pada)
        <form method="POST" action="{{ route('ujian.bukaKunci', $pengerjaan) }}" class="mb-4">
            @csrf
            <button class="nb-btn merah">Buka kunci siswa ini</button>
        </form>
    @endif

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    @if ($kejadians->isNotEmpty())
        <div class="nb-scroll">
            <table class="nb-table">
                <thead><tr><th>Jam</th><th>Menit ke-</th><th>Kejadian</th><th>Keterangan</th><th>Dihitung</th></tr></thead>
                <tbody>
                    @foreach ($kejadians as $k)
                        <tr>
                            <td>{{ $k->terjadi_pada->format('H.i.s') }}</td>
                            <td>{{ (int) floor($pengerjaan->mulai_pada->diffInSeconds($k->terjadi_pada, false) / 60) }}</td>
                            <td>{{ \App\Services\Anticurang::label($k->jenis) }}</td>
                            <td>{{ $k->detail }}</td>
                            <td>{{ $k->dihitung ? 'Ya' : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="text-sm mt-3">
        "Kembali ke halaman ujian" menunjukkan berapa lama siswa pergi. Catatan ini petunjuk, bukan bukti:
        jaringan yang putus atau notifikasi di HP juga bisa membuat halaman kehilangan fokus. Tanyakan dulu ke siswanya.
    </p>
</x-guru-layout>
