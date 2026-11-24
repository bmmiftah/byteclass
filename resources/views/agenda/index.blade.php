<x-guru-layout :judul="'Agenda ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-2">Agenda {{ $kelas->nama }}</h1>
    <p class="mb-6">Tenggat tugas dan jadwal ujian otomatis muncul di kalender siswa. Di sini lu menambah acara lain: kegiatan, libur, atau hal penting.</p>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <a href="{{ route('agenda.create', $kelas) }}" class="nb-btn kuning mb-6 inline-block">+ Agenda baru</a>

    <h2 class="text-xl mb-2">Akan datang</h2>

    <div class="space-y-3 mb-8">
        @forelse ($akan as $a)
            @include('agenda._baris', ['a' => $a])
        @empty
            <div class="nb-card">
                <p class="font-bold">Belum ada agenda mendatang.</p>
            </div>
        @endforelse
    </div>

    @if ($lalu->isNotEmpty())
        <h2 class="text-xl mb-2">Sudah lewat</h2>

        <div class="space-y-3">
            @foreach ($lalu as $a)
                @include('agenda._baris', ['a' => $a])
            @endforeach
        </div>
    @endif
</x-guru-layout>