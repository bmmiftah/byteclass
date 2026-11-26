@php
    // [label, nama route, ikon] - semua route menerima $kelas
    $grup = [
        'Mengajar' => [
            ['Materi',     'materi.index',     'materi'],
            ['Tugas',      'tugas.index',      'tugas'],
            ['Ujian CBT',  'ujian.index',      'ujian'],
            ['Pengumuman', 'pengumuman.index', 'pengumuman'],
            ['Agenda',     'agenda.index',     'agenda'],
        ],
        'Di kelas' => [
            ['Presensi',           'presensi.index', 'presensi'],
            ['Denah Tempat Duduk', 'denah.index',    'denah'],
            ['Kelompok',           'kelompok.index', 'kelompok'],
            ['Alat Kelas',         'alat.index',     'alat'],
            ['Catatan',            'catatan.index',  'catatan'],
        ],
        'Nilai & siswa' => [
            ['Penilaian',       'penilaian.index',      'penilaian'],
            ['Rekap Nilai',     'penilaian.rekapKelas', 'rekap'],
            ['Leger Nilai',     'leger.index',          'leger'],
            ['Poin & Lencana',  'poin.index',           'poin'],
            ['Kode Siswa',      'akun.index',           'kode'],
        ],
    ];
@endphp
<x-guru-layout :judul="$kelas->nama">
    <a href="{{ route('kelas.index') }}" class="nb-btn putih kecil"><x-ikon nama="kembali" /> Semua kelas</a>

    {{-- Kepala kelas --}}
    <div class="nb-card mt-4 mb-8" style="background: {{ $kelas->warna_hex }}">
        <h1 class="text-3xl">{{ $kelas->nama }}</h1>
        <div class="flex flex-wrap gap-2 mt-2">
            @if ($kelas->tahun_ajaran)
                <span class="nb-chip">{{ $kelas->tahun_ajaran }}</span>
            @endif
            <span class="nb-chip">Semester {{ $kelas->semester }}</span>
            <span class="nb-chip">{{ $siswas->count() }} siswa</span>
        </div>
    </div>

    @if (session('ok'))
        <div class="nb-card mb-6" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    {{-- Menu kelas, dikelompokkan --}}
    @foreach ($grup as $namaGrup => $item)
        <section class="mb-6">
            <h2 class="nb-grup">{{ $namaGrup }}</h2>
            <div class="nb-menu-grid" style="--w: {{ $kelas->warna_hex }}">
                @foreach ($item as [$label, $rute, $ikon])
                    <x-kartu-menu :href="route($rute, $kelas)" :ikon="$ikon" :label="$label" />
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Daftar siswa --}}
    <h2 class="text-2xl mt-10 mb-4">Daftar siswa</h2>

    <div class="grid gap-4 mb-6">
        <details class="nb-acc" {{ $siswas->isEmpty() || $errors->has('nama') || $errors->has('nis') || $errors->has('gender') ? 'open' : '' }}>
            <summary><x-ikon nama="tambah" /> Tambah siswa</summary>
            <form method="POST" action="{{ route('siswa.store', $kelas) }}" class="nb-acc-isi grid gap-4 md:grid-cols-5 items-end">
                @csrf

                <div class="md:col-span-2">
                    <label class="nb-label" for="nama">Nama siswa</label>
                    <input class="nb-input" id="nama" name="nama" value="{{ old('nama') }}" required>
                    @error('nama') <p class="nb-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="nb-label" for="nis">NIS</label>
                    <input class="nb-input" id="nis" name="nis" value="{{ old('nis') }}">
                    @error('nis') <p class="nb-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="nb-label" for="gender">L/P</label>
                    <select class="nb-input" id="gender" name="gender">
                        <option value="">-</option>
                        <option value="L" @selected(old('gender') === 'L')>L</option>
                        <option value="P" @selected(old('gender') === 'P')>P</option>
                    </select>
                    @error('gender') <p class="nb-error">{{ $message }}</p> @enderror
                </div>

                <button class="nb-btn"><x-ikon nama="tambah" /> Tambah</button>
            </form>
        </details>

        <details class="nb-acc" {{ $errors->has('file') ? 'open' : '' }}>
            <summary><x-ikon nama="unggah" /> Impor dari Excel/CSV</summary>
            <form method="POST" action="{{ route('siswa.import', $kelas) }}" enctype="multipart/form-data" class="nb-acc-isi grid gap-4">
                @csrf

                <div>
                    <label class="nb-label" for="file">Pilih berkas</label>
                    <input class="nb-input" type="file" id="file" name="file" accept=".xlsx,.xls,.csv" required>
                    <p class="text-sm mt-2">Baris pertama harus berisi judul kolom: <b>nis, nama, nama_panggilan, gender</b></p>
                    @error('file')
                        <p class="nb-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button class="nb-btn"><x-ikon nama="unggah" /> Impor</button>
                </div>
            </form>
        </details>
    </div>

    <div class="nb-card nb-scroll">
        <table class="nb-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>L/P</th>
                    <th><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($siswas as $s)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $s->nis }}</td>
                        <td>{{ $s->nama }}</td>
                        <td>{{ $s->gender }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('siswa.destroy', $s) }}" onsubmit="return confirm('Hapus siswa ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="nb-btn merah kecil" aria-label="Hapus {{ $s->nama }}"><x-ikon nama="hapus" /> <span class="hidden sm:inline">Hapus</span></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Belum ada siswa. Tambah satu per satu atau impor dari Excel.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pengaturan kelas: warna, arsip, hapus --}}
    <details class="nb-acc bahaya mt-10" {{ $errors->has('warna') ? 'open' : '' }}>
        <summary><x-ikon nama="pengaturan" /> Pengaturan kelas</summary>
        <div class="nb-acc-isi grid gap-6">
            <form method="POST" action="{{ route('kelas.warna', $kelas) }}" class="grid gap-3">
                @csrf
                <x-pilih-warna :terpilih="$kelas->warna" />
                @error('warna') <p class="nb-error">{{ $message }}</p> @enderror
                <div>
                    <button class="nb-btn kecil">Simpan warna</button>
                </div>
            </form>

            <div class="nb-pisah">
                <p class="font-bold mb-1">Zona hati-hati</p>
                <p class="text-sm mb-3">Arsip menyembunyikan kelas, datanya tetap tersimpan dan bisa dipulihkan. Hapus membuang kelas ini.</p>
                <div class="nb-aksi">
                    <form method="POST" action="{{ route('kelas.arsipkan', $kelas) }}" onsubmit="return confirm('Arsipkan kelas ini? Data tetap tersimpan dan bisa dipulihkan.')">
                        @csrf
                        <button class="nb-btn putih"><x-ikon nama="arsip" /> Arsipkan kelas</button>
                    </form>

                    <form method="POST" action="{{ route('kelas.destroy', $kelas) }}" onsubmit="return confirm('Hapus kelas ini?')">
                        @csrf
                        @method('DELETE')
                        <button class="nb-btn merah"><x-ikon nama="hapus" /> Hapus kelas</button>
                    </form>
                </div>
            </div>
        </div>
    </details>
</x-guru-layout>
