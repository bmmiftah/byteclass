<x-guru-layout :judul="$tugas->judul">
    <a href="{{ route('tugas.index', $tugas->kelas_id) }}" class="font-bold underline">
        &larr; Tugas {{ $tugas->kelas->nama }}
    </a>

    <div class="flex flex-wrap items-start justify-between gap-3 mt-3 mb-4">
        <div>
            <h1 class="text-3xl">{{ $tugas->judul }}</h1>
            <p class="mt-1">
                Tenggat {{ $tugas->tenggatTampil() }} &middot; {{ \App\Models\Tugas::JENIS[$tugas->jenis] }}
                @if ($tugas->mapel)
                    &middot; {{ $tugas->mapel->nama }}
                @endif
            </p>
            @if ($tugas->terjadwal)
                <p class="text-sm">
                    Tayang di siswa mulai {{ $tugas->terbit_pada->locale('id')->translatedFormat('j F Y H.i') }}.
                </p>
            @endif
        </div>

        <span class="nb-chip" style="background: {{ $tugas->warnaStatus() }}">{{ $tugas->labelStatus() }}</span>
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

    {{-- Aksi utama --}}
    <div class="flex flex-wrap gap-2 mb-6">
        @unless ($tugas->file_dibersihkan_pada)
            <a class="nb-btn putih" href="{{ route('tugas.edit', $tugas) }}">Ubah</a>
        @endunless

        @if ($tugas->status === 'draf')
            <form method="POST" action="{{ route('tugas.terbit', $tugas) }}">
                @csrf
                <button class="nb-btn hijau">Terbitkan</button>
            </form>
        @elseif ($tugas->status === 'terbit')
            <form method="POST" action="{{ route('tugas.arsip', $tugas) }}">
                @csrf
                <button class="nb-btn biru">Arsipkan</button>
            </form>
            @if ($kumpul->isEmpty())
                <form method="POST" action="{{ route('tugas.tarik', $tugas) }}">
                    @csrf
                    <button class="nb-btn putih">Tarik jadi draf</button>
                </form>
            @endif
        @elseif ($tugas->status === 'arsip' && ! $tugas->file_dibersihkan_pada)
            <form method="POST" action="{{ route('tugas.bukaArsip', $tugas) }}">
                @csrf
                <button class="nb-btn putih">Buka arsip</button>
            </form>
        @endif

        @if ($jumlahFile > 0)
            <a class="nb-btn kuning" href="{{ route('tugas.zip', $tugas) }}">Unduh ZIP ({{ $jumlahFile }} file)</a>
        @endif

        @if ($kumpul->whereNotNull('nilai')->isNotEmpty())
            <form method="POST" action="{{ route('tugas.kirimNilai', $tugas) }}">
                @csrf
                <button class="nb-btn pink">Kirim nilai ke Penilaian</button>
            </form>
        @endif
    </div>

    {{-- Ringkasan --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <span class="nb-chip">{{ $ringkas['kumpul'] }} dari {{ $ringkas['siswa'] }} mengumpulkan</span>
        <span class="nb-chip">{{ $ringkas['dinilai'] }} dinilai</span>
        @if ($ringkas['telat'])
            <span class="nb-chip" style="background: var(--oranye)">{{ $ringkas['telat'] }} telat</span>
        @endif
    </div>

    {{-- Rubrik penilaian (Fase 9D) --}}
    <div class="nb-card mb-6" id="kartu-rubrik">
        @if ($kriterias->isEmpty())
            <p class="font-bold">Nilai dengan rubrik</p>
            @if ($rubriks->isEmpty())
                <p class="text-sm mt-1">Belum ada rubrik. Buat dulu di menu <a class="underline font-bold" href="{{ route('rubrik.index') }}">Rubrik</a>, lalu pasang di sini.</p>
            @else
                <p class="text-sm mt-1">Pasang rubrik supaya nilai diisi per kriteria dan totalnya dihitung otomatis. Tanpa rubrik, nilai diisi langsung (0 sampai 100).</p>
                <form method="POST" action="{{ route('tugas.rubrik.pasang', $tugas) }}" class="mt-2 flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="nb-label" for="rubrik_id">Rubrik</label>
                        <select class="nb-input" style="width: 18rem" id="rubrik_id" name="rubrik_id" required>
                            <option value="">- pilih rubrik -</option>
                            @foreach ($rubriks as $r)
                                <option value="{{ $r->id }}">{{ $r->nama }} ({{ $r->kriterias_count }} kriteria)</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="nb-btn kuning" id="pasang-rubrik">Pasang rubrik</button>
                </form>
            @endif
        @else
            <p class="font-bold">Rubrik penilaian tugas ini</p>
            <div class="nb-scroll mt-2">
                <table class="nb-table">
                    <thead>
                        <tr>
                            <th>Kriteria</th>
                            <th>Jenis</th>
                            <th>Bobot</th>
                            <th>Porsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $jumlahBobot = max(1, (int) $kriterias->sum('bobot')); @endphp
                        @foreach ($kriterias as $k)
                            <tr>
                                <td>{{ $k->nama }}</td>
                                <td>{{ ['angka' => 'Angka 0-' . $k->maks, 'poin' => 'Poin 1-' . $k->maks, 'ceklis' => 'Ya/Tidak'][$k->tipe] ?? $k->tipe }}</td>
                                <td>{{ $k->bobot }}</td>
                                <td>{{ (int) round($k->bobot / $jumlahBobot * 100) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-sm mt-2">Ini salinan dari rubrik di pustaka: mengubah rubrik aslinya nanti tidak mengubah tugas ini.</p>
            <form method="POST" action="{{ route('tugas.rubrik.lepas', $tugas) }}" class="mt-2" onsubmit="return confirm('Lepas rubrik? Nilai total siswa tetap ada, tetapi rincian per kriteria dihapus.')">
                @csrf
                <button class="nb-btn putih" id="lepas-rubrik">Lepas rubrik</button>
            </form>
        @endif
    </div>

    @if ($tugas->jenis === 'eksternal')
        <div class="nb-card mb-6">
            <p class="font-bold">Folder pengumpulan</p>
            <a class="underline break-all" href="{{ $tugas->tautan_eksternal }}" target="_blank" rel="noopener noreferrer">
                {{ $tugas->tautan_eksternal }}
            </a>
            @if ($tugas->kode_konfirmasi)
                <p class="text-sm mt-1">Kode konfirmasi: <b>{{ $tugas->kode_konfirmasi }}</b></p>
            @endif
            <p class="text-sm mt-1">
                Siswa hanya menekan konfirmasi. Cek file mereka sendiri di folder itu, lalu tekan Simpan di barisnya untuk memverifikasi.
            </p>
        </div>
    @endif

    {{-- Pembersihan file --}}
    @if ($tugas->status === 'arsip')
        <div class="nb-card mb-6" style="background: {{ $tugas->file_dibersihkan_pada ? '#eee' : 'var(--kuning)' }}">
            @if ($tugas->file_dibersihkan_pada)
                <p class="font-bold">File sudah dibersihkan</p>
                <p class="text-sm">
                    Pada {{ $tugas->file_dibersihkan_pada->locale('id')->translatedFormat('j F Y H.i') }}.
                    Nilai, komentar, dan waktu pengumpulan tetap tersimpan.
                </p>
            @else
                <p class="font-bold">Bersihkan file tugas</p>
                <p class="text-sm">
                    Sekarang tersimpan: <b>{{ $jumlahFile }} file</b>, <b>{{ \Illuminate\Support\Number::fileSize($pakaiTugas, 1) }}</b>.
                    Yang dihapus hanya filenya. Nilai, komentar, status, dan waktu kumpul tetap ada.
                </p>

                @if ($tugas->hapus_pada)
                    <p class="mt-2 font-bold">
                        File dihapus otomatis {{ $tugas->hapus_pada->locale('id')->translatedFormat('j F Y') }}
                        ({{ $sisaHari > 0 ? $sisaHari . ' hari lagi' : 'hari ini' }}).
                    </p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <form method="POST" action="{{ route('tugas.tunda', $tugas) }}">
                            @csrf
                            <button class="nb-btn putih">Tunda {{ config('tugas.hari_tunda', 14) }} hari</button>
                        </form>
                        <form method="POST" action="{{ route('tugas.tunda', $tugas) }}">
                            @csrf
                            <input type="hidden" name="aksi" value="batal">
                            <button class="nb-btn putih">Batalkan hapus otomatis</button>
                        </form>
                    </div>
                @endif

                @if ($jumlahFile > 0)
                    <div class="mt-3">
                        <p class="font-bold">Langkah 1: unduh ZIP dulu supaya ada cadangan.</p>
                        <a class="nb-btn biru inline-block mt-1" href="{{ route('tugas.zip', $tugas) }}">Unduh ZIP</a>
                    </div>
                    <form
                        method="POST"
                        action="{{ route('tugas.bersihkan', $tugas) }}"
                        class="mt-3 flex flex-wrap gap-3 items-end"
                        onsubmit="return confirm('Hapus {{ $jumlahFile }} file? File yang sudah dihapus tidak bisa dikembalikan.')"
                    >
                        @csrf
                        <div>
                            <label class="nb-label" for="pw-bersih">Langkah 2: password kamu</label>
                            <input
                                class="nb-input"
                                style="width: 14rem"
                                type="password"
                                id="pw-bersih"
                                name="password"
                                autocomplete="current-password"
                                required
                            >
                        </div>
                        <button class="nb-btn merah">Bersihkan file</button>
                    </form>
                @endif
            @endif
        </div>
    @endif

    {{-- Daftar siswa --}}
    <div class="space-y-3">
        @foreach ($siswas as $s)
            @php
                $p = $kumpul->get($s->id);
                if (! $p) {
                    $label = 'Belum';
                    $bg = 'var(--kuning)';
                } elseif ($p->nilai !== null) {
                    $label = 'Dinilai';
                    $bg = 'var(--hijau)';
                } elseif ($p->terverifikasi_pada) {
                    $label = 'Diverifikasi';
                    $bg = 'var(--biru)';
                } else {
                    $label = 'Menunggu';
                    $bg = 'var(--pink)';
                }
            @endphp

            <div class="nb-card muncul">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-bold text-lg">{{ $s->nama }}</p>
                    <div class="flex flex-wrap gap-2 items-center">
                        @if ($p?->telat)
                            <span class="nb-chip" style="background: var(--oranye)">Telat</span>
                        @endif
                        @if ($p?->ditandai_guru)
                            <span class="nb-chip">Ditandai guru</span>
                        @endif
                        <span class="nb-chip" style="background: {{ $bg }}">{{ $label }}</span>
                    </div>
                </div>

                @if ($p)
                    <p class="text-sm mt-1">
                        Dikumpulkan {{ $p->dikirim_pada->locale('id')->translatedFormat('j F Y H.i') }}
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

                    @if ($p->berkas->isNotEmpty())
                        <ul class="mt-2 space-y-1">
                            @foreach ($p->berkas as $b)
                                <li class="text-sm">
                                    @if ($b->ada)
                                        <a class="underline font-bold" href="{{ route('tugas.berkas', $b) }}">{{ $b->nama_asli }}</a>
                                        ({{ \Illuminate\Support\Number::fileSize($b->ukuran, 1) }})
                                    @else
                                        {{ $b->nama_asli }} <span class="nb-chip">dibersihkan</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Form nilai: per kriteria kalau ada rubrik, satu kolom kalau tidak --}}
                    @if ($kriterias->isNotEmpty())
                        @php $pakaiOld = (int) old('pid') === $p->id; @endphp
                        <form method="POST" action="{{ route('tugas.nilai', $p) }}" class="mt-3 form-rubrik" id="rf{{ $p->id }}">
                            @csrf
                            <input type="hidden" name="pid" value="{{ $p->id }}">
                            <div class="grid gap-2 md:grid-cols-2">
                                @foreach ($kriterias as $k)
                                    @php
                                        $v = $skor[$p->id][$k->id] ?? null;
                                        $v = $pakaiOld ? old('kriteria.' . $k->id, $v) : $v;
                                        $nama = 'r' . $p->id . '-' . $k->id;
                                    @endphp
                                    <div>
                                        <label class="nb-label" for="{{ $nama }}">
                                            {{ $k->nama }}
                                            <span class="nb-chip">{{ $k->tipe === 'ceklis' ? 'Ya/Tidak' : ($k->tipe === 'poin' ? '1-' . $k->maks : '0-' . $k->maks) }} &middot; bobot {{ $k->bobot }}</span>
                                        </label>
                                        @if ($k->tipe === 'ceklis')
                                            <select class="nb-input" id="{{ $nama }}" name="kriteria[{{ $k->id }}]">
                                                <option value="">-</option>
                                                <option value="1" @selected($v !== null && $v !== '' && (float) $v > 0)>Ya</option>
                                                <option value="0" @selected($v !== null && $v !== '' && (float) $v == 0)>Tidak</option>
                                            </select>
                                        @elseif ($k->tipe === 'poin')
                                            <input class="nb-input" type="number" step="1" min="1" max="{{ $k->maks }}" id="{{ $nama }}" name="kriteria[{{ $k->id }}]" value="{{ $v !== null && $v !== '' ? (int) $v : '' }}">
                                        @else
                                            <input class="nb-input" type="number" step="0.01" min="0" max="{{ $k->maks }}" id="{{ $nama }}" name="kriteria[{{ $k->id }}]" value="{{ $v !== null && $v !== '' ? rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') : '' }}">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-2 grid gap-2 md:grid-cols-6 items-end">
                                <div class="md:col-span-5">
                                    <label class="nb-label" for="k{{ $p->id }}">Komentar untuk siswa</label>
                                    <input class="nb-input" id="k{{ $p->id }}" name="komentar" maxlength="1000" value="{{ $p->komentar }}">
                                </div>
                                <button class="nb-btn biru">Simpan</button>
                            </div>
                            <p class="text-sm mt-1">
                                @if ($p->nilai !== null)
                                    <span class="nb-chip" style="background: var(--hijau)">Nilai total: {{ rtrim(rtrim(number_format((float) $p->nilai, 2, ',', ''), '0'), ',') }}</span>
                                    @if (empty($skor[$p->id]))
                                        Nilai ini diisi manual sebelum ada rubrik; menyimpan nilai rubrik akan menggantinya.
                                    @endif
                                @endif
                                Isi semua kriteria, atau kosongkan semuanya untuk hanya memverifikasi.
                            </p>
                        </form>
                    @else
                        <form method="POST" action="{{ route('tugas.nilai', $p) }}" class="mt-3 grid gap-2 md:grid-cols-6 items-end">
                            @csrf
                            <div>
                                <label class="nb-label" for="n{{ $p->id }}">Nilai</label>
                                <input class="nb-input" type="number" step="0.01" min="0" max="100" id="n{{ $p->id }}" name="nilai" value="{{ $p->nilai !== null ? rtrim(rtrim(number_format((float) $p->nilai, 2, '.', ''), '0'), '.') : '' }}">
                            </div>
                            <div class="md:col-span-4">
                                <label class="nb-label" for="k{{ $p->id }}">Komentar untuk siswa</label>
                                <input class="nb-input" id="k{{ $p->id }}" name="komentar" maxlength="1000" value="{{ $p->komentar }}">
                            </div>
                            <button class="nb-btn biru">Simpan</button>
                        </form>
                        <p class="text-sm mt-1">Simpan tanpa nilai = hanya verifikasi. Kosongkan nilai lalu simpan untuk membuka kunci kumpul ulang.</p>
                    @endif
                @elseif ($tugas->status !== 'draf')
                    <form method="POST" action="{{ route('tugas.tandai', [$tugas, $s]) }}" class="mt-2">
                        @csrf
                        <button class="nb-btn putih">Tandai sudah kumpul</button>
                    </form>
                @endif
            </div>
        @endforeach

        @if ($siswas->isEmpty())
            <div class="nb-card">
                <p class="font-bold">Belum ada siswa aktif di kelas ini.</p>
            </div>
        @endif
    </div>

    {{-- Hapus tugas --}}
    <details class="nb-card mt-8">
        <summary class="font-bold cursor-pointer">Hapus tugas ini</summary>
        <form
            method="POST"
            action="{{ route('tugas.destroy', $tugas) }}"
            class="mt-3 flex flex-wrap gap-3 items-end"
            onsubmit="return confirm('Hapus tugas beserta semua nilai dan file? Tidak bisa dikembalikan.')"
        >
            @csrf
            @method('DELETE')
            <div>
                <label class="nb-label" for="pw-hapus">Password kamu</label>
                <input
                    class="nb-input"
                    style="width: 14rem"
                    type="password"
                    id="pw-hapus"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>
            <button class="nb-btn merah">Hapus permanen</button>
        </form>
    </details>
</x-guru-layout>