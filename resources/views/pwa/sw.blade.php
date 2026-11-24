/* Service worker Ayo Belajar (hanya mengurus alamat di bawah /saya/). Versi: {{ $versi }} */
const VERSI = 'ayo-{{ $versi }}';
const STATIK = VERSI + '-statik';
const HALAMAN = 'ayo-halaman'; // nama tetap: dibersihkan saat siswa keluar
const OFFLINE = '/saya/offline';
const MAKS_HALAMAN = 30;

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIK);
        const res = await fetch(OFFLINE, { cache: 'reload' });
        await cache.put(OFFLINE, res.clone());

        // Simpan juga CSS/JS yang dipakai halaman offline supaya tampil rapi tanpa internet
        const html = await res.text();
        const aset = [...html.matchAll(/(?:href|src)="([^"]*\/build\/assets\/[^"]+)"/g)].map((m) => m[1]);
        await Promise.all(aset.map((u) => cache.add(u).catch(() => {})));
        await cache.addAll(['/pwa/icon-192.png', '/pwa/icon-512.png']).catch(() => {});
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const nama = await caches.keys();
        await Promise.all(nama.filter((n) => n !== STATIK && n !== HALAMAN).map((n) => caches.delete(n)));
        await self.clients.claim();
    })());
});

// Halaman yang tidak boleh disimpan di perangkat: login, unduhan file, dan sw sendiri
function bolehDisimpan(url) {
    const p = url.pathname;
    if (p === '/saya/masuk' || p === '/saya/sw.js' || p === OFFLINE) return false;
    if (/\/berkas\//.test(p)) return false;
    return p === '/saya' || p.startsWith('/saya/');
}

async function rapikanHalaman(cache) {
    const kunci = await cache.keys();
    for (let i = 0; i < kunci.length - MAKS_HALAMAN; i++) {
        await cache.delete(kunci[i]);
    }
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // File CSS/JS hasil build: nama berubah tiap versi, jadi aman diambil dari simpanan dulu
    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/pwa/')) {
        event.respondWith((async () => {
            const cache = await caches.open(STATIK);
            const ada = await cache.match(req);
            if (ada) return ada;

            const res = await fetch(req);
            if (res.ok) cache.put(req, res.clone());
            return res;
        })());
        return;
    }

    // Halaman: ambil dari jaringan dulu; kalau offline, pakai salinan terakhir atau halaman offline
    if (req.mode === 'navigate' && (url.pathname === '/saya' || url.pathname.startsWith('/saya/'))) {
        event.respondWith((async () => {
            try {
                const res = await fetch(req);
                const jenis = res.headers.get('content-type') || '';
                if (res.ok && !res.redirected && jenis.includes('text/html') && bolehDisimpan(url)) {
                    const cache = await caches.open(HALAMAN);
                    await cache.put(req, res.clone());
                    await rapikanHalaman(cache);
                }
                return res;
            } catch (e) {
                const salinan = await (await caches.open(HALAMAN)).match(req);
                return salinan || (await (await caches.open(STATIK)).match(OFFLINE)) || Response.error();
            }
        })());
    }
});

// Pesan dari halaman: 'bersihkan' = buang semua halaman tersimpan (dipanggil saat keluar / di halaman masuk)
self.addEventListener('message', (event) => {
    if (event.data === 'bersihkan') {
        event.waitUntil(caches.delete(HALAMAN));
    }
});

// Notifikasi push
self.addEventListener('push', (event) => {
    let d = {};
    try {
        d = event.data ? event.data.json() : {};
    } catch (e) {
        d = { judul: 'Ayo Belajar', isi: event.data ? event.data.text() : '' };
    }

    event.waitUntil((async () => {
        await self.registration.showNotification(d.judul || 'Ayo Belajar', {
            body: d.isi || '',
            icon: '/pwa/icon-192.png',
            badge: '/pwa/badge-96.png',
            tag: d.tag || 'ayo',
            lang: 'id',
            data: { url: d.url || '/saya' },
        });

        if (typeof d.badge === 'number' && self.navigator && self.navigator.setAppBadge) {
            try {
                d.badge > 0 ? await self.navigator.setAppBadge(d.badge) : await self.navigator.clearAppBadge();
            } catch (e) {}
        }
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const tujuan = new URL((event.notification.data && event.notification.data.url) || '/saya', self.location.origin).href;

    event.waitUntil((async () => {
        const jendela = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const j of jendela) {
            if (j.url === tujuan && 'focus' in j) return j.focus();
        }
        for (const j of jendela) {
            if ('navigate' in j && 'focus' in j) {
                await j.navigate(tujuan);
                return j.focus();
            }
        }
        return self.clients.openWindow(tujuan);
    })());
});