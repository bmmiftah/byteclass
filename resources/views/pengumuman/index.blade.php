<x-guru-layout :judul="'Pengumuman ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">Pengumuman {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <a href="{{ route('pengumuman.create', $kelas) }}" class="nb-btn kuning mb-6 inline-block">+ Pengumuman baru</a>

    <div class="space-y-3">
        @forelse ($daftar as $p)
            @php $warna = ['tayang' => 'var(--hijau)', 'terjadwal' => 'var(--kuning)', 'berakhir' => '#ddd'][$p->status]; @endphp
            <div class="nb-card muncul">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex-1 min-w-[200px]">
                        <p class="font-bold text-lg">
                            @if ($p->disematkan)
                                <span aria-label="Disematkan">&#9733;</span>
                            @endif
                            {{ $p->judul }}
                        </p>
                        <p class="text-sm whitespace-pre-line">{{ \Illuminate\Support\Str::limit($p->isi, 200) }}</p>
                        <p class="text-sm mt-1">
                            @if ($p->terbit_pada)
                                Tayang {{ $p->terbit_pada->locale('id')->translatedFormat('j F Y H.i') }}.
                            @endif
                            @if ($p->berakhir_pada)
                                Berakhir {{ $p->berakhir_pada->locale('id')->translatedFormat('j F Y H.i') }}.
                            @endif
                        </p>
                    </div>
                    <span class="nb-chip" style="background: {{ $warna }}">{{ ['tayang' => 'Tayang', 'terjadwal' => 'Terjadwal', 'berakhir' => 'Berakhir'][$p->status] }}</span>
                </div>

                <div class="flex flex-wrap gap-2 mt-3">
                    <a class="nb-btn putih" href="{{ route('pengumuman.edit', $p) }}">Ubah</a>
                    <form method="POST" action="{{ route('pengumuman.destroy', $p) }}" onsubmit="return confirm('Hapus pengumuman ini?')">
                        @csrf
                        @method('DELETE')
                        <button class="nb-btn merah">Hapus</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="nb-card">
                <p class="font-bold">Belum ada pengumuman.</p>
                <p>Buat lewat tombol "+ Pengumuman baru".</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>