<?php

return [
    // Kunci VAPID untuk notifikasi push. Buat dengan: php artisan pengingat:kunci
    'vapid' => [
        'public'  => env('VAPID_PUBLIC_KEY'),
        'private' => env('VAPID_PRIVATE_KEY'),
        // Harus berupa alamat email (mailto:...) atau https://domain-lu
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    ],

    // Notifikasi push hanya boleh dikirim ke layanan push milik browser besar ini.
    // (Mencegah siswa mendaftarkan alamat sembarang supaya server mengirim permintaan ke sana.)
    'host_push' => [
        'fcm.googleapis.com',
        'push.services.mozilla.com',
        'push.apple.com',
        'notify.windows.com',
    ],

    // Jendela pengingat tugas (menit sebelum tenggat), dari yang paling mendesak
    'tugas' => [
        '3j'  => 180,
        '24j' => 1440,
    ],

    // Ujian: pengingat sekian menit sebelum dibuka
    'ujian_menit' => 60,

    // Catatan "sudah dikirim" dibuang setelah sekian hari
    'simpan_hari' => 14,
];