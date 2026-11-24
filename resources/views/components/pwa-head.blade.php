{{-- Ditaruh di <head> halaman siswa: manifest, ikon, dan pendaftaran service worker --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#FF8C42">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/png" sizes="192x192" href="/pwa/icon-192.png">
<link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Ayo Belajar">

<script>
    // Tawaran "pasang aplikasi" datang cepat; simpan dulu sampai tombolnya siap
    window.__pasang = null;

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        window.__pasang = e;
        window.dispatchEvent(new Event('bisa-pasang'));
    });

    window.addEventListener('appinstalled', function () {
        window.__pasang = null;
        window.dispatchEvent(new Event('sudah-pasang'));
    });

    // Buang halaman yang tersimpan di HP (dipanggil saat keluar, dan otomatis di halaman masuk)
    window.bersihkanCacheSaya = function () {
        if (!('serviceWorker' in navigator)) return;

        navigator.serviceWorker.getRegistration('/').then(function (r) {
            if (r && r.active) r.active.postMessage('bersihkan');
        }).catch(function () {});
    };

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/saya/sw.js', { scope: '/' }).then(function () {
                @if (request()->routeIs('saya.masuk'))
                    window.bersihkanCacheSaya();
                @endif
            }).catch(function () {});
        });
    }
</script>