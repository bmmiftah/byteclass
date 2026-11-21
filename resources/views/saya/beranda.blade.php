<x-siswa-layout judul="Dashboard Saya" :kelas="$kelas">
    {{-- Sapaan --}}
    <div class="nb-card mb-6 muncul" style="background: {{ $kelas->warna_hex }}">
        <p class="text-sm font-bold">Dashboard saya</p>
        <h1 class="text-3xl md:text-4xl">Halo, {{ $siswa->nama_panggilan ?: strtok($siswa->nama, ' ') }}!</h1>
        <p class="mt-1">{{ $kelas->nama }} &middot; {{ $kelas->tahun_ajaran }} &middot; Semester {{ $kelas->semester }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3 mb-8">
        <a
            href="{{ route('siswa.kelas', $kelas->kode_publik) }}"
            class="nb-card hover muncul block"
            style="background: #fff; animation-delay: 60ms"
        >
            <p class="judul text-4xl">&rarr;</p>
            <p class="font-bold">Materi kelas</p>
        </a>

        <a
            href="{{ route('cbt.daftar') }}"
            class="nb-card hover muncul block"
            style="background: var(--hijau); animation-delay: 120ms"
        >
            <p class="judul text-4xl">{{ $ujianBuka }}</p>
            <p class="font-bold">Ujian yang dibuka</p>
        </a>

        <div class="nb-card muncul" style="background: #fff; animation-delay: 180ms">
            <p class="judul text-4xl">&hellip;</p>
            <p class="font-bold">Tugas &amp; agenda</p>
            <p class="text-sm">Segera hadir.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('saya.keluar') }}">
        @csrf
        <button class="nb-btn putih">Keluar</button>
    </form>
</x-siswa-layout>