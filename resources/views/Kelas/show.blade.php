<x-guru-layout :judul="$kelas->nama">
    <a href="{{ route('kelas.index') }}" class="font-bold underline">&larr; Semua kelas</a>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3 mb-6">
        <div>
            <h1 class="text-3xl">{{ $kelas->nama }}</h1>
            <p>{{ $kelas->tahun_ajaran }} &middot; Semester {{ $kelas->semester }} &middot; {{ $siswas->count() }} siswa</p>
        </div>

        <form method="POST" action="{{ route('kelas.destroy', $kelas) }}" onsubmit="return confirm('Hapus kelas ini?')">
            @csrf
            @method('DELETE')
            <button class="nb-btn merah">Hapus Kelas</button>
        </form>
    </div>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <form method="POST" action="{{ route('siswa.store', $kelas) }}" class="nb-card mb-6 grid gap-4 md:grid-cols-5 items-end">
        @csrf

        <div class="md:col-span-2">
            <label class="nb-label" for="nama">Nama siswa</label>
            <input class="nb-input" id="nama" name="nama" required>
        </div>

        <div>
            <label class="nb-label" for="nis">NIS</label>
            <input class="nb-input" id="nis" name="nis">
        </div>

        <div>
            <label class="nb-label" for="gender">L/P</label>
            <select class="nb-input" id="gender" name="gender">
                <option value="">-</option>
                <option value="L">L</option>
                <option value="P">P</option>
            </select>
        </div>

        <button class="nb-btn">+ Siswa</button>
    </form>

    <form method="POST" action="{{ route('siswa.import', $kelas) }}" enctype="multipart/form-data" class="nb-card mb-6 flex flex-wrap items-end gap-4" style="background: var(--biru)">
        @csrf

        <div class="flex-1 min-w-[220px]">
            <label class="nb-label" for="file">Impor dari Excel/CSV</label>
            <input class="nb-input" type="file" id="file" name="file" accept=".xlsx,.xls,.csv" required>
            <p class="text-sm mt-1">Baris pertama harus berisi judul kolom: <b>nis, nama, nama_panggilan, gender</b></p>
            @error('file')
                <p class="nb-error">{{ $message }}</p>
            @enderror
        </div>

        <button class="nb-btn putih">Impor</button>
    </form>
    
    <div class="nb-card nb-scroll">
        <table class="nb-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>L/P</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($siswas as $s)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $s->nis }}</td>
                        <td>{{ $s->nama }}</td>
                        <td>{{ $s->gender }}</td>
                        <td>
                            <form method="POST" action="{{ route('siswa.destroy', $s) }}" onsubmit="return confirm('Hapus siswa ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="font-bold underline">Hapus</button>
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
</x-guru-layout>