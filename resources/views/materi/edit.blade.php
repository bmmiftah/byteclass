<x-guru-layout :judul="'Edit ' . $bab->judul">
    <a href="{{ route('materi.index', $bab->kelas_id) }}" class="font-bold underline">&larr; Materi {{ $bab->kelas->nama }}</a>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3 mb-6">
        <h1 class="text-3xl">{{ $bab->judul }}</h1>
        <span
            class="nb-chip"
            style="background: {{ ['Draft' => '#fff', 'Dijadwalkan' => 'var(--kuning)', 'Terbit' => 'var(--hijau)'][$bab->labelStatus()] }}"
        >
            {{ $bab->labelStatus() }}
        </span>
    </div>

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

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Kiri: pengaturan bab + QR --}}
        <div class="space-y-6">
            <form method="POST" action="{{ route('materi.update', $bab) }}" class="nb-card space-y-4">
                @csrf
                @method('PUT')

                <h2 class="text-lg">Pengaturan bab</h2>

                <div>
                    <label class="nb-label" for="judul">Judul</label>
                    <input class="nb-input" id="judul" name="judul" value="{{ old('judul', $bab->judul) }}" required>
                </div>

                <div>
                    <label class="nb-label" for="mapel_id">Mata pelajaran</label>
                    <select class="nb-input" id="mapel_id" name="mapel_id">
                        <option value="">Umum</option>
                        @foreach ($mapels as $m)
                            <option value="{{ $m->id }}" @selected(old('mapel_id', $bab->mapel_id) == $m->id)>{{ $m->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="nb-label" for="deskripsi">Deskripsi singkat (opsional)</label>
                    <textarea class="nb-input" id="deskripsi" name="deskripsi" rows="3">{{ old('deskripsi', $bab->deskripsi) }}</textarea>
                </div>

                <div>
                    <label class="nb-label" for="status">Status</label>
                    <select class="nb-input" id="status" name="status">
                        <option value="draft" @selected(old('status', $bab->status) === 'draft')>Draft (disembunyikan)</option>
                        <option value="terbit" @selected(old('status', $bab->status) === 'terbit')>Terbit</option>
                    </select>
                </div>

                <div>
                    <label class="nb-label" for="terbit_pada">Jadwal terbit (opsional)</label>
                    <input
                        class="nb-input"
                        type="datetime-local"
                        id="terbit_pada"
                        name="terbit_pada"
                        value="{{ old('terbit_pada', $bab->terbit_pada?->format('Y-m-d\TH:i')) }}"
                    >
                    <p class="text-xs mt-1">
                        Status "Terbit" + tanggal di masa depan = muncul otomatis pada waktu itu. Kosongkan untuk langsung terbit.
                    </p>
                </div>

                <button class="nb-btn w-full">Simpan bab</button>
            </form>

            @if ($bab->bisaDilihat())
                @php $linkBab = route('siswa.bab', [$bab->kelas->kode_publik, $bab]); @endphp

                <div
                    class="nb-card"
                    style="background: var(--kuning)"
                    x-data="{
                        unduh() {
                            const a = document.createElement('a');
                            a.href = this.$refs.qr.toDataURL('image/png');
                            a.download = 'qr-bab-{{ $bab->id }}.png';
                            a.click();
                        }
                    }"
                    x-init="$nextTick(() => QRCode.toCanvas($refs.qr, '{{ $linkBab }}', { width: 160, margin: 1 }))"
                >
                    <h2 class="text-lg mb-2">QR bab ini</h2>
                    <canvas x-ref="qr" class="border-2 border-black bg-white mx-auto"></canvas>

                    <div class="flex flex-wrap gap-2 mt-3">
                        <button type="button" class="nb-btn putih" @click="unduh()">Unduh QR</button>
                        <a class="nb-btn putih" href="{{ $linkBab }}" target="_blank">Lihat sebagai siswa</a>
                    </div>
                </div>
            @else
                <div class="nb-card">
                    <p class="font-bold">Belum terlihat siswa.</p>
                    <p class="text-sm">
                        QR dan tombol "Lihat sebagai siswa" muncul setelah bab berstatus Terbit dan jadwalnya tiba.
                    </p>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('materi.destroy', $bab) }}"
                onsubmit="return confirm('Hapus bab ini beserta semua materi dan file di dalamnya?')"
            >
                @csrf
                @method('DELETE')
                <button class="font-bold underline">Hapus bab ini</button>
            </form>
        </div>

        {{-- Kanan: item materi --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Tambah materi --}}
            <form
                method="POST"
                action="{{ route('materi.item.store', $bab) }}"
                enctype="multipart/form-data"
                class="nb-card space-y-4"
                style="background: var(--biru)"
                x-data="{ tipe: 'file' }"
            >
                @csrf

                <h2 class="text-lg">Tambah materi</h2>

                <div>
                    <label class="nb-label" for="tipe">Jenis</label>
                    <select class="nb-input" id="tipe" name="tipe" x-model="tipe">
                        <option value="file">File (PDF, PPT, Word, Excel, gambar)</option>
                        <option value="link">Video / Tautan</option>
                        <option value="teks">Teks</option>
                    </select>
                </div>

                <div>
                    <label class="nb-label" for="judul_item">Judul materi</label>
                    <input class="nb-input" id="judul_item" name="judul" required placeholder="mis. Slide Pertemuan 1">
                </div>

                <div x-show="tipe === 'file'">
                    <label class="nb-label" for="berkas">Pilih file (maks 20 MB)</label>
                    <input
                        class="nb-input bg-white"
                        type="file"
                        id="berkas"
                        name="berkas"
                        accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.gif"
                    >
                    <p class="text-xs mt-1">
                        Gambar JPG/PNG/WEBP otomatis dikecilkan. Untuk video, pakai link YouTube/Drive.
                    </p>
                </div>

                <div x-show="tipe === 'link'" style="display: none">
                    <label class="nb-label" for="url">Alamat (URL)</label>
                    <input
                        class="nb-input"
                        type="url"
                        id="url"
                        name="url"
                        placeholder="https://www.youtube.com/watch?v=..."
                    >
                    <p class="text-xs mt-1">
                        Link YouTube dan Google Drive (file) otomatis tampil sebagai pemutar di halaman siswa.
                    </p>
                </div>

                <div x-show="tipe === 'teks'" style="display: none">
                    <label class="nb-label" for="isi">Isi teks</label>
                    <textarea
                        class="nb-input"
                        id="isi"
                        name="isi"
                        rows="8"
                        placeholder="Gunakan # Judul, - daftar, **tebal**"
                    ></textarea>
                    <p class="text-xs mt-1">
                        Format sederhana (Markdown): <code># Judul</code>, <code>- butir</code>, <code>1. nomor</code>,
                        <code>**tebal**</code>, <code>*miring*</code>.
                    </p>
                </div>

                <button class="nb-btn putih w-full">+ Tambahkan</button>
            </form>

            {{-- Daftar item --}}
            <div class="space-y-3">
                @forelse ($bab->items as $item)
                    <div class="nb-card flex flex-wrap items-center gap-3">
                        <span class="ikon-tipe">{{ $item->label() }}</span>

                        <div class="flex-1 min-w-[180px]">
                            <p class="font-bold">{{ $item->judul }}</p>
                            <p class="text-sm">
                                @if ($item->tipe === 'file')
                                    {{ $item->nama_asli }} &middot; {{ \Illuminate\Support\Number::fileSize($item->ukuran ?? 0, 1) }}
                                @elseif ($item->url)
                                    {{ \Illuminate\Support\Str::limit($item->url, 60) }}
                                @else
                                    {{ \Illuminate\Support\Str::limit(strip_tags($item->isi), 60) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('materi.item.pindah', $item) }}">
                                @csrf
                                <input type="hidden" name="arah" value="naik">
                                <button class="nb-btn putih" style="padding: .25rem .6rem" aria-label="Naik">&uarr;</button>
                            </form>

                            <form method="POST" action="{{ route('materi.item.pindah', $item) }}">
                                @csrf
                                <input type="hidden" name="arah" value="turun">
                                <button class="nb-btn putih" style="padding: .25rem .6rem" aria-label="Turun">&darr;</button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('materi.item.destroy', $item) }}"
                                onsubmit="return confirm('Hapus materi ini?')"
                            >
                                @csrf
                                @method('DELETE')
                                <button class="nb-btn merah" style="padding: .25rem .6rem" aria-label="Hapus">X</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="nb-card">
                        <p>Belum ada materi di bab ini.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-guru-layout>