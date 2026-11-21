<x-guru-layout :judul="'Kode Siswa '.$kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">
        &larr; {{ $kelas->nama }}
    </a>

    <h1 class="text-3xl mt-3 mb-6">Kode Siswa {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">
            {{ session('ok') }}
        </div>
    @endif

    <div class="flex flex-wrap gap-3 mb-6">
        <form method="POST" action="{{ route('akun.buat', $kelas) }}">
            @csrf
            <button class="nb-btn">Buat kode untuk yang belum punya</button>
        </form>

        <a class="nb-btn kuning" href="{{ route('akun.kartu', $kelas) }}" target="_blank">Cetak kartu login</a>

        <a class="nb-btn biru" href="{{ route('akun.kodemasuk', $kelas) }}">Kode Masuk (PNG)</a>
    </div>

    <p class="mb-3 text-sm">
        Alamat masuk siswa: <b>{{ url('/cbt') }}</b>. Kode bersifat rahasia; jangan ditempel di tempat umum.
    </p>

    <div class="nb-scroll">
        <table class="nb-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Kode</th>
                    <th>Terakhir masuk</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($siswas as $s)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $s->nama }}</td>
                        <td class="judul">{{ $s->akun?->kode_tampil ?? '-' }}</td>
                        <td>{{ $s->akun?->terakhir_masuk?->translatedFormat('d M H:i') ?? '-' }}</td>
                        <td>
                            <form
                                method="POST"
                                action="{{ route('akun.reset', $s) }}"
                                onsubmit="return confirm('Ganti kode {{ $s->nama }}? Kartu lama tidak berlaku lagi.')"
                            >
                                @csrf
                                <button class="font-bold underline">
                                    {{ $s->akun ? 'Ganti kode' : 'Buat kode' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Belum ada siswa aktif.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-guru-layout>