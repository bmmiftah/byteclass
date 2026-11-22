<?php

return [
    // Kuota penyimpanan hosting (GB), hanya untuk angka & peringatan. Kosongkan bila tidak mau.
    'kuota_gb' => env('KUOTA_HOSTING_GB'),

    // Berapa hari "Tunda" menggeser jadwal hapus otomatis
    'hari_tunda' => 14,

    // Peringatan "sebentar lagi dihapus" muncul bila sisa hari <= ini
    'hari_peringatan' => 7,
];