{{-- Ikon garis sederhana (24x24). Pakai: <x-ikon nama="kelas" /> atau <x-ikon nama="kelas" class="w-6 h-6" />
     Daftar tetap di bawah ini; nama yang tidak ada tidak menampilkan apa-apa. --}}
@props(['nama'])
@php
    $daftar = [
        'beranda'    => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'kelas'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M17 14.2c2.6.2 4.5 2.1 4.5 5.3"/>',
        'pustaka'    => '<rect x="3" y="4" width="4" height="16"/><rect x="9" y="4" width="4" height="16"/><path d="M15.5 6.5l3.8-1 3 14-3.8 1z"/>',
        'mapel'      => '<path d="M5 3h13v18H7a2 2 0 0 1-2-2z"/><path d="M9 7h6"/><path d="M9 11h6"/>',
        'soal'       => '<rect x="5" y="4" width="14" height="17"/><path d="M9 2h6v4H9z"/><path d="M8.5 13l2.5 2.5 4.5-5"/>',
        'jurnal'     => '<path d="M5 3h14v18H5z"/><path d="M9 3v18"/><path d="M12.5 8H16"/><path d="M12.5 12H16"/>',
        'admin'      => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2.2 2.2L15.5 10"/>',
        'keluar'     => '<path d="M9 4H4v16h5"/><path d="M16 8l4 4-4 4"/><path d="M20 12H9"/>',
        'lainnya'    => '<circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/>',
        'materi'     => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M9 12h6"/><path d="M9 16h6"/>',
        'presensi'   => '<rect x="3" y="5" width="18" height="16"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M9 15l2 2 4-4"/>',
        'denah'      => '<rect x="3" y="3" width="18" height="18"/><path d="M3 9h18"/><path d="M9 9v12"/>',
        'kelompok'   => '<circle cx="12" cy="7" r="3"/><circle cx="5.5" cy="16" r="2.5"/><circle cx="18.5" cy="16" r="2.5"/><path d="M8 21c0-2.5 1.8-4 4-4s4 1.5 4 4"/>',
        'alat'       => '<circle cx="12" cy="13.5" r="7.5"/><path d="M12 9.5v4l2.5 2"/><path d="M9.5 2.5h5"/>',
        'catatan'    => '<path d="M4 4h16v10l-6 6H4z"/><path d="M14 20v-6h6"/>',
        'penilaian'  => '<path d="M12 3l2.8 5.8 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 3 1.1-6.2L3 9.7l6.2-.9z"/>',
        'rekap'      => '<rect x="3" y="4" width="18" height="16"/><path d="M3 10h18"/><path d="M3 15h18"/><path d="M9 4v16"/>',
        'kode'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9"/><path d="M16 7l3 3"/>',
        'ujian'      => '<rect x="3" y="4" width="18" height="12"/><path d="M8 21h8"/><path d="M12 16v5"/>',
        'tugas'      => '<rect x="5" y="4" width="14" height="17"/><path d="M9 2h6v4H9z"/><path d="M9 11h6"/><path d="M9 15h6"/>',
        'leger'      => '<path d="M5 3h14v18H5z"/><path d="M5 9h14"/><path d="M5 15h14"/><path d="M11 9v12"/>',
        'pengumuman' => '<path d="M3 10v4h4l8 5V5l-8 5z"/><path d="M18.5 9a4 4 0 0 1 0 6"/>',
        'agenda'     => '<rect x="3" y="5" width="18" height="16"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'poin'       => '<path d="M7 4h10v6a5 5 0 0 1-10 0z"/><path d="M7 6H3v2a4 4 0 0 0 4 4"/><path d="M17 6h4v2a4 4 0 0 1-4 4"/><path d="M12 15v4"/><path d="M8 21h8"/>',
        'warna'      => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
        'pengaturan' => '<path d="M4 7h10"/><path d="M18 7h2"/><circle cx="16" cy="7" r="2"/><path d="M4 17h2"/><path d="M10 17h10"/><circle cx="8" cy="17" r="2"/>',
        'arsip'      => '<rect x="3" y="4" width="18" height="4"/><path d="M5 8v12h14V8"/><path d="M10 12h4"/>',
        'hapus'      => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 14h10l1-14"/>',
        'tambah'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'unggah'     => '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 20h16"/>',
        'kembali'    => '<path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/>',
        'tutup'      => '<path d="M5 5l14 14"/><path d="M19 5L5 19"/>',
    ];
@endphp
@if (isset($daftar[$nama]))
<svg {{ $attributes->merge(['class' => 'ikon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false">{!! $daftar[$nama] !!}</svg>
@endif
