<x-guru-layout :judul="'Tugas ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">Tugas {{ $kelas->nama }}</h1>

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

    @if ($penuh)
        <div class="nb-card mb-4" style="background: var(--oranye)">
            <p class="font-bold">Penyimpanan server hampir penuh.</p>
            <p>Unduh ZIP lalu bersihkan file tugas lama yang sudah selesai dinilai.</p>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 mb-8">
        <div class="nb-card">
            <p class="font-bold">Penyimpanan file tugas</p>
            <p class="text-sm mt-1">Kelas ini: <b>{{ \Illuminate\Support\Number::fileSize($pakaiKelas, 1) }}</b></p>
            <p class="text-sm">Semua kelasmu: <b>{{ \Illuminate\Support\Number::fileSize($pakaiGuru, 1) }}</b></p>
            <p class="text-sm mt-2">Foto dari HP otomatis dikecilkan supaya hemat tempat.</p>
        </div>

        <form
            method="POST"
            action="{{ route('tugas.pengaturan') }}"
            class="nb-card"
            x-data="{ hari: '{{ auth()->user()->hapus_otomatis_hari }}' }"
        >
            @csrf
            <p class="font-bold">Hapus file otomatis</p>
            <p class="text-sm mt-1">
                Setelah tugas diarsipkan, file dihapus otomatis sekian hari kemudian. Nilai dan komentar tetap tersimpan. Standarnya mati.
            </p>
            <div class="mt-3 flex flex-wrap gap-3 items-end">
                <div>
                    <label class="nb-label" for="hari">Hari (min. 7)</label>
                    <input
                        class="nb-input"
                        style="width: 7rem"
                        type="number"
                        id="hari"
                        name="hari"
                        min="7"
                        max="365"
                        x-model="hari"
                        placeholder="mati"
                    >
                </div>
                <div x-show="hari" x-cloak>
                    <label class="nb-label" for="pw">Password</label>
                    <input
                        class="nb-input"
                        style="width: 12rem"
                        type="password"
                        id="pw"
                        name="password"
                        autocomplete="current-password"
                    >
                </div>
                <button class="nb-btn biru">Simpan</button>
            </div>
            <p class="text-sm mt-2">
                Status: <b>{{ auth()->user()->hapus_otomatis_hari ? 'aktif, ' . auth()->user()->hapus_otomatis_hari . ' hari' : 'mati' }}</b>.
                Mengaktifkan atau mengubah butuh password; mematikan tidak.
            </p>
        </form>
    </div>

    <a href="{{ route('tugas.create', $kelas) }}" class="nb-btn kuning mb-6 inline-block">+ Tugas baru</a>

    <div class="space-y-3">
        @forelse ($tugas as $t)
            <a
                href="{{ route('tugas.hasil', $t) }}"
                class="nb-card flex flex-wrap items-center gap-3 justify-between muncul block"
            >
                <div class="flex-1 min-w-[200px]">
                    <p class="font-bold text-lg">{{ $t->judul }}</p>
                    <p class="text-sm">Tenggat {{ $t->tenggatTampil() }} &middot; {{ \App\Models\Tugas::JENIS[$t->jenis] }}</p>
                    <p class="text-sm">
                        {{ $t->pengumpulans_count }} dari {{ $jumlahSiswa }} siswa mengumpulkan
                        @if ($t->file_dibersihkan_pada)
                            &middot; file sudah dibersihkan
                        @endif
                        @if ($t->hapus_pada && ! $t->file_dibersihkan_pada)
                            &middot; file dihapus {{ $t->hapus_pada->locale('id')->diffForHumans() }}
                        @endif
                    </p>
                </div>
                <span class="nb-chip" style="background: {{ $t->warnaStatus() }}">{{ $t->labelStatus() }}</span>
            </a>
        @empty
            <div class="nb-card">
                <p class="font-bold">Belum ada tugas.</p>
                <p>Buat lewat tombol "+ Tugas baru".</p>
            </div>
        @endforelse
    </div>
</x-guru-layout>