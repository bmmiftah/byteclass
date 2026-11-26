@php
    $menu = [
        ['saya.beranda', 'Beranda'],
        ['saya.tugas', 'Tugas'],
        ['saya.kalender', 'Kalender'],
        ['saya.agenda', 'Agenda'],
        ['saya.pengumuman', 'Pengumuman'],
    ];

    // Fase 9F: menu Poin hanya muncul kalau guru menghidupkan gamifikasi untuk kelas siswa ini
    $akunNav = request()->attributes->get('akun');
    if (\App\Models\GamifikasiPengaturan::aktifUntuk($akunNav?->siswa?->kelas_id)) {
        $menu[] = ['saya.poin', 'Poin'];
    }

@endphp

<nav class="flex flex-wrap gap-2 mb-6" aria-label="Menu Dashboard Saya">
    @foreach ($menu as [$rute, $label])
        <a
            href="{{ route($rute) }}"
            class="nb-btn {{ request()->routeIs($rute === 'saya.beranda' ? $rute : $rute . '*') ? '' : 'putih' }}"
        >{{ $label }}</a>
    @endforeach
</nav>