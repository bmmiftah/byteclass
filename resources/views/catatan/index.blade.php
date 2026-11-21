<x-guru-layout :judul="'Catatan ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">Catatan &amp; Poin {{ $kelas->nama }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    @verbatim
        <script>
            window.poinKelas = (url, total) => ({
                total,
                pesan: '',

                async tambah(id, jenis) {
                    try {
                        const r = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ siswa_id: id, jenis, poin: 1 }),
                        });

                        if (!r.ok) throw new Error('gagal');

                        const d = await r.json();
                        this.total[id] = d.total;
                        this.pesan = 'Tersimpan';
                    } catch (e) {
                        this.pesan = 'Gagal menyimpan, coba lagi';
                    }

                    setTimeout(() => this.pesan = '', 1500);
                },
            });
        </script>
    @endverbatim

    <div x-data="poinKelas('{{ route('catatan.store', $kelas) }}', {{ \Illuminate\Support\Js::from((object) $total->all()) }})">
        <p class="mb-3 font-bold h-6" x-text="pesan"></p>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($siswas as $sw)
                <div class="nb-card flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold truncate">{{ $sw->nama }}</p>
                        <p
                            class="judul text-2xl"
                            :style="total[{{ $sw->id }}] < 0 ? 'color:#b00020' : ''"
                            x-text="total[{{ $sw->id }}]"
                        ></p>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="nb-btn merah"
                            style="padding: .3rem .8rem"
                            aria-label="Kurangi poin"
                            @click="tambah({{ $sw->id }}, 'minus')"
                        >-1</button>

                        <button
                            type="button"
                            class="nb-btn hijau"
                            style="padding: .3rem .8rem"
                            aria-label="Tambah poin"
                            @click="tambah({{ $sw->id }}, 'plus')"
                        >+1</button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Catatan lengkap --}}
    <form method="POST" action="{{ route('catatan.store', $kelas) }}" class="nb-card my-8 grid gap-4 md:grid-cols-6 items-end">
        @csrf

        <h2 class="text-lg md:col-span-6">Tambah catatan dengan keterangan</h2>

        <div class="md:col-span-2">
            <label class="nb-label" for="siswa_id">Siswa</label>
            <select class="nb-input" id="siswa_id" name="siswa_id" required>
                @foreach ($siswas as $sw)
                    <option value="{{ $sw->id }}">{{ $sw->nama }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="nb-label" for="jenis">Jenis</label>
            <select class="nb-input" id="jenis" name="jenis">
                <option value="plus">Poin +</option>
                <option value="minus">Poin -</option>
                <option value="catatan">Catatan saja</option>
            </select>
        </div>

        <div>
            <label class="nb-label" for="poin">Poin</label>
            <input class="nb-input" type="number" id="poin" name="poin" min="0" max="100" value="1">
        </div>

        <div class="md:col-span-2">
            <label class="nb-label" for="tanggal">Tanggal</label>
            <input class="nb-input" type="date" id="tanggal" name="tanggal" value="{{ today()->toDateString() }}">
        </div>

        <div class="md:col-span-5">
            <label class="nb-label" for="isi">Keterangan</label>
            <input
                class="nb-input"
                id="isi"
                name="isi"
                maxlength="1000"
                placeholder="mis. membantu teman saat praktik"
            >
        </div>

        <button class="nb-btn">Simpan</button>
    </form>

    {{-- Riwayat --}}
    <h2 class="text-2xl mb-3">30 catatan terbaru</h2>

    <div class="space-y-3">
        @forelse ($terbaru as $c)
            <div class="nb-card flex flex-wrap items-center gap-3">
                <span
                    class="nb-chip"
                    style="background: {{ $c->poin > 0 ? 'var(--hijau)' : ($c->poin < 0 ? 'var(--merah)' : '#fff') }}"
                >
                    {{ $c->poin > 0 ? '+' . $c->poin : $c->poin }}
                </span>

                <div class="flex-1 min-w-[180px]">
                    <p class="font-bold">{{ $c->siswa->nama }}</p>
                    <p class="text-sm">
                        {{ $c->tanggal->translatedFormat('d M Y') }}@if ($c->isi) &middot; {{ $c->isi }}@endif
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('catatan.destroy', $c) }}"
                    onsubmit="return confirm('Hapus catatan ini?')"
                >
                    @csrf
                    @method('DELETE')
                    <button class="font-bold underline">Hapus</button>
                </form>
            </div>
        @empty
            <div class="nb-card">
                <p>Belum ada catatan.</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>