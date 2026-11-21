<x-cbt-layout judul="Daftar Ujian">
    <main class="max-w-2xl mx-auto p-4 py-8">
        <div
            class="nb-card mb-8 flex flex-wrap items-center justify-between gap-3"
            style="background: var(--biru)"
        >
            <div>
                <p class="text-sm">Kamu masuk sebagai</p>
                <p class="judul text-2xl">{{ $siswa->nama }}</p>
                <p class="font-bold">{{ $siswa->kelas->nama }}</p>
            </div>

            <form method="POST" action="{{ route('cbt.keluar') }}">
                @csrf
                <button class="nb-btn putih">Bukan aku / Keluar</button>
            </form>
        </div>

        <h1 class="text-3xl mb-4">Ujian untukmu</h1>

        <div class="space-y-3">
            @forelse ($ujians as $u)
                @php $p = $ps[$u->id] ?? null; @endphp

                <div class="nb-card muncul" style="animation-delay: {{ $loop->index * 60 }}ms">
                    <p class="font-bold text-lg">{{ $u->judul }}</p>
                    <p class="text-sm mb-3">{{ $u->durasi }} menit</p>

                    @if ($p && $p->status === 'selesai')
                        <a class="nb-btn putih" href="{{ route('cbt.hasil', $u) }}">Sudah selesai</a>
                    @elseif ($p)
                        <a class="nb-btn hijau" href="{{ route('cbt.kerjakan', $u) }}">Lanjutkan</a>
                    @elseif ($u->bisaMulai())
                        <a class="nb-btn" href="{{ route('cbt.token', $u) }}">Mulai</a>
                    @else
                        <span class="nb-chip" style="background: var(--kuning)">Belum dibuka</span>
                    @endif
                </div>
            @empty
                <div class="nb-card">
                    <p class="font-bold">Belum ada ujian yang dibuka.</p>
                    <p>Tunggu aba-aba dari gurumu, lalu muat ulang halaman ini.</p>
                </div>
            @endforelse
        </div>
    </main>
</x-cbt-layout>