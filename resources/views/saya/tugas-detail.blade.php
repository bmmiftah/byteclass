<x-siswa-layout :judul="$tugas->judul" :kelas="$kelas">
    <a href="{{ route('saya.tugas') }}" class="font-bold underline">&larr; Semua tugas</a>

    <div class="nb-card mt-3 mb-6" style="background: {{ $kelas->warna_hex }}">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h1 class="text-3xl">{{ $tugas->judul }}</h1>
            <span class="nb-chip" style="background: {{ $status[2] }}">{{ $status[1] }}</span>
        </div>
        <p class="mt-1">Tenggat {{ $tugas->tenggatTampil() }} ({{ $tugas->sisaWaktu() }})</p>
        @if ($tugas->lewat_tenggat && $tugas->telat === 'terima' && $tugas->status === 'terbit')
            <p class="text-sm font-bold">Sudah lewat tenggat, tapi masih diterima dengan label telat.</p>
        @endif
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

    @if ($tugas->instruksi)
        <div class="nb-card mb-6 teks-materi">{!! $tugas->instruksi !!}</div>
    @endif

    {{-- Yang sudah dikumpulkan --}}
    @if ($p)
        <div class="nb-card mb-6">
            <p class="font-bold">Pengumpulanmu</p>
            <p class="text-sm">
                {{ $p->dikirim_pada->locale('id')->translatedFormat('j F Y H.i') }}@if ($p->telat) &middot; telat @endif
            </p>

            @if ($p->tautan)
                <p class="mt-2">
                    <a class="underline break-all" href="{{ $p->tautan }}" target="_blank" rel="noopener noreferrer nofollow">
                        {{ $p->tautan }}
                    </a>
                </p>
            @endif

            @if ($p->teks)
                <div class="mt-2 border-2 border-black p-2 bg-white whitespace-pre-line">{{ $p->teks }}</div>
            @endif

            @foreach ($p->berkas as $b)
                <p class="text-sm mt-1">
                    @if ($b->ada)
                        <a class="underline font-bold" href="{{ route('saya.tugas.berkas', [$tugas->id, $b->id]) }}">
                            {{ $b->nama_asli }}
                        </a>
                    @else
                        {{ $b->nama_asli }} <span class="nb-chip">sudah dihapus dari server</span>
                    @endif
                </p>
            @endforeach

            @if ($tugas->jenis === 'eksternal')
                <p class="text-sm mt-1">
                    Kamu sudah mengonfirmasi pengumpulan.
                    {{ $p->terverifikasi_pada ? 'Gurumu sudah memeriksa.' : 'Menunggu diperiksa guru.' }}
                </p>
            @endif

            @if ($p->nilai !== null)
                <p class="mt-3 text-2xl judul">
                    Nilai: {{ rtrim(rtrim(number_format((float) $p->nilai, 2, ',', ''), '0'), ',') }}
                </p>
            @endif

            @if (count($rincian) && $p->nilai !== null)
                <div class="mt-2 text-sm" id="rincian-nilai">
                    @foreach ($rincian as $r)
                        <p>{{ $r['nama'] }}: <b>{{ \App\Services\RubrikTugas::teks($r) }}</b></p>
                    @endforeach
                </div>
            @endif

            @if ($p->komentar)
                <div class="mt-2 border-2 border-black p-2" style="background: var(--kuning)">
                    <b>Komentar guru:</b> {{ $p->komentar }}
                </div>
            @endif
        </div>
    @endif

    @if (count($rincian))
        <div class="nb-card mb-4" id="kartu-rubrik-siswa">
            <p class="font-bold">Cara penilaian (rubrik)</p>
            <ul class="text-sm mt-1 list-disc pl-5">
                @foreach ($rincian as $r)
                    <li>{{ $r['nama'] }} &middot; {{ $r['porsi'] }}% dari nilai ({{ $r['tipe'] === 'ceklis' ? 'Ya/Tidak' : 'skala ' . $r['maks'] }})</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Formulir kumpul --}}
    @if ($bisaUbah)
        @if ($tugas->jenis === 'file')
            @vite('resources/js/tugas.js')
        @endif

        <form
            method="POST"
            action="{{ route('saya.tugas.kumpul', $tugas->id) }}"
            enctype="multipart/form-data"
            class="nb-card space-y-4"
            data-tugas-form
        >
            @csrf
            <p class="font-bold text-lg">{{ $p ? 'Kumpulkan ulang' : 'Kumpulkan tugas' }}</p>

            @if ($p && $tugas->jenis === 'file')
                <p class="text-sm">File yang kamu pilih akan <b>menggantikan</b> file sebelumnya.</p>
            @endif

            @if ($tugas->jenis === 'file')
                <div>
                    <label class="nb-label" for="berkas">
                        Pilih file (maks. {{ $tugas->maks_berkas }} file, {{ $tugas->ukuran_mb }} MB per file)
                    </label>
                    <input
                        class="nb-input"
                        type="file"
                        id="berkas"
                        name="berkas[]"
                        multiple
                        data-tugas-berkas
                        data-maks="{{ $tugas->maks_berkas }}"
                        data-mb="{{ $tugas->ukuran_mb }}"
                        data-ekstensi="{{ implode(',', $tugas->ekstensiBoleh()) }}"
                        accept="{{ collect($tugas->ekstensiBoleh())->map(fn ($e) => '.' . $e)->implode(',') }}"
                    >
                    <p class="text-sm mt-1">
                        Boleh: {{ strtoupper(implode(', ', $tugas->ekstensiBoleh())) }}. Foto besar otomatis dikecilkan.
                    </p>
                    <p class="text-sm font-bold mt-1" data-tugas-pesan hidden style="color: #b00020"></p>
                    <ul class="mt-2 space-y-1 text-sm" data-tugas-daftar></ul>
                </div>
            @elseif ($tugas->jenis === 'link')
                <div>
                    <label class="nb-label" for="tautan">Tempel link tugasmu</label>
                    <input
                        class="nb-input"
                        type="url"
                        id="tautan"
                        name="tautan"
                        placeholder="https://..."
                        value="{{ old('tautan', $p?->tautan) }}"
                        required
                    >
                    <p class="text-sm mt-1">
                        Pastikan link bisa dibuka gurumu (atur aksesnya jadi "siapa saja yang punya link").
                    </p>
                </div>
            @elseif ($tugas->jenis === 'teks')
                <div>
                    <label class="nb-label" for="teks">Jawabanmu</label>
                    <textarea
                        class="nb-input"
                        id="teks"
                        name="teks"
                        rows="8"
                        maxlength="5000"
                        required
                    >{{ old('teks', $p?->teks) }}</textarea>
                </div>
            @else
                <div class="space-y-3">
                    <p>1. Buka folder pengumpulan, lalu unggah tugasmu di sana.</p>
                    <a
                        class="nb-btn biru inline-block"
                        href="{{ $tugas->tautan_eksternal }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Buka folder pengumpulan</a>
                    <p>2. Beri nama filemu seperti ini supaya mudah dikenali:</p>
                    <p class="nb-chip" style="word-break: break-all">{{ $saran }}</p>
                    @if ($tugas->kode_konfirmasi)
                        <div>
                            <label class="nb-label" for="kode">3. Ketik kode konfirmasi (ada di nama folder)</label>
                            <input
                                class="nb-input"
                                id="kode"
                                name="kode"
                                maxlength="20"
                                autocomplete="off"
                                value="{{ old('kode') }}"
                            >
                        </div>
                    @endif
                    <label class="flex items-center gap-2 font-bold">
                        <input type="checkbox" name="yakin" value="1"> Aku sudah mengumpulkan tugasku di folder itu
                    </label>
                </div>
            @endif

            <button class="nb-btn kuning" data-tugas-kirim>
                {{ $tugas->jenis === 'eksternal' ? 'Konfirmasi' : 'Kirim tugas' }}
            </button>
        </form>
    @elseif (! $p)
        <div class="nb-card" style="background: var(--merah)">
            <p class="font-bold">Tugas ini sudah ditutup.</p>
            <p class="text-sm">Kalau ada alasan khusus, bicara dengan gurumu.</p>
        </div>
    @endif
</x-siswa-layout>