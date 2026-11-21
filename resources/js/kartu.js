/* Kartu "Kode Masuk": digambar langsung di <canvas>, jadi PNG-nya persis sama dengan pratinjau.
   Tanpa library tambahan; ZIP dibuat sendiri (tanpa kompresi, PNG memang sudah terkompresi). */
const W = 1080;
const H = 1500;
const INK = '#111111';

// Warna kelas => [warna latar, warna pasangan untuk kotak kelas]
const WARNA = {
    kuning: ['#FFD93D', '#FF6B9D'],
    biru:   ['#4D96FF', '#FFD93D'],
    hijau:  ['#6BCB77', '#FFD93D'],
    oranye: ['#FF8C42', '#FFD93D'],
    pink:   ['#FF6B9D', '#FFD93D'],
};

const F_JUDUL = (px) => `700 ${px}px "Space Grotesk", "Inter", sans-serif`;
const F_KODE  = (px) => `700 ${px}px "Space Mono", ui-monospace, monospace`;
const F_TEKS  = (px) => `700 ${px}px "Inter", system-ui, sans-serif`;
const F_BIASA = (px) => `500 ${px}px "Inter", system-ui, sans-serif`;

/* ---------- alat gambar ---------- */

// Teks dengan jarak antar huruf (ctx.letterSpacing belum ada di semua browser)
function teks(ctx, str, x, y, { font, warna = INK, jarak = 0, rata = 'left' }) {
    ctx.font = font;
    ctx.fillStyle = warna;
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'left';

    const huruf = [...str];
    const lebar = huruf.reduce((a, h) => a + ctx.measureText(h).width + jarak, 0) - (huruf.length ? jarak : 0);

    let kiri = x;
    if (rata === 'right') kiri = x - lebar;
    if (rata === 'center') kiri = x - lebar / 2;

    for (const h of huruf) {
        ctx.fillText(h, kiri, y);
        kiri += ctx.measureText(h).width + jarak;
    }

    return lebar;
}

function ukur(ctx, str, font, jarak = 0) {
    ctx.font = font;
    const huruf = [...str];
    return huruf.reduce((a, h) => a + ctx.measureText(h).width + jarak, 0) - (huruf.length ? jarak : 0);
}

function kotak(ctx, x, y, w, h, { isi = '#fff', garis = 7, bayangan = 10 } = {}) {
    if (bayangan) {
        ctx.fillStyle = INK;
        ctx.fillRect(x + bayangan, y + bayangan, w, h);
    }

    ctx.fillStyle = INK;
    ctx.fillRect(x, y, w, h);
    ctx.fillStyle = isi;
    ctx.fillRect(x + garis, y + garis, w - garis * 2, h - garis * 2);
}

// Pecah teks menjadi baris; null kalau ada satu kata yang lebih lebar dari kotak
function bungkus(ctx, str, lebarMaks, font) {
    ctx.font = font;
    const kata = str.trim().split(/\s+/);
    const baris = [];
    let sekarang = '';

    for (const k of kata) {
        if (ctx.measureText(k).width > lebarMaks) return null;

        const coba = sekarang ? sekarang + ' ' + k : k;
        if (ctx.measureText(coba).width <= lebarMaks) {
            sekarang = coba;
        } else {
            baris.push(sekarang);
            sekarang = k;
        }
    }

    baris.push(sekarang);
    return baris;
}

function potongTitik(ctx, str, lebarMaks) {
    let s = str;
    while (s.length > 1 && ctx.measureText(s + '…').width > lebarMaks) s = s.slice(0, -1);
    return s + '…';
}

// Cari ukuran huruf terbesar yang membuat nama muat dalam 2 baris; paling kecil 34px lalu dipotong "…"
export function cocokkanNama(ctx, nama, lebarMaks, maksBaris = 2) {
    for (let px = 72; px >= 34; px -= 2) {
        const baris = bungkus(ctx, nama, lebarMaks, F_JUDUL(px));
        if (baris && baris.length <= maksBaris) return { px, baris };
    }

    // Terpaksa dipotong: pecah per karakter di ukuran terkecil
    const font = F_JUDUL(34);
    ctx.font = font;
    const baris = [];
    let sekarang = '';

    for (const h of [...nama.trim()]) {
        if (ctx.measureText(sekarang + h).width > lebarMaks && baris.length < maksBaris) {
            baris.push(sekarang.trim());
            sekarang = h;
        } else {
            sekarang += h;
        }
    }

    baris.push(sekarang.trim());
    const dipotong = baris.slice(0, maksBaris);

    if (baris.length > maksBaris || ctx.measureText(dipotong[maksBaris - 1]).width > lebarMaks) {
        dipotong[maksBaris - 1] = potongTitik(ctx, dipotong[maksBaris - 1], lebarMaks);
    }

    return { px: 34, baris: dipotong };
}

// Teks campuran tebal/biasa yang dibungkus ke beberapa baris. bagian: [[teks, tebal?], ...]
function paragraf(ctx, bagian, x, y, lebarMaks, tinggiBaris, px) {
    const token = [];
    for (const [t, tebal] of bagian) {
        for (const k of t.split(/(\s+)/)) if (k) token.push({ k, tebal });
    }

    const barisList = [[]];
    let lebar = 0;

    for (const t of token) {
        const w = ukur(ctx, t.k, t.tebal ? F_TEKS(px) : F_BIASA(px));
        if (lebar + w > lebarMaks && !/^\s+$/.test(t.k) && barisList.at(-1).length) {
            barisList.push([]);
            lebar = 0;
        }

        if (/^\s+$/.test(t.k) && !barisList.at(-1).length) continue;

        barisList.at(-1).push(t);
        lebar += w;
    }

    barisList.forEach((baris, i) => {
        let kiri = x;
        for (const t of baris) {
            const font = t.tebal ? F_TEKS(px) : F_BIASA(px);
            teks(ctx, t.k, kiri, y + i * tinggiBaris, { font });
            kiri += ukur(ctx, t.k, font);
        }
    });

    return barisList.length;
}

/* ---------- kartu ---------- */

/**
 * Gambar satu kartu ke canvas.
 * data = { nama, kelas, warna, situs, kodeKelas, kodePribadi }
 * skala: 1 = ukuran PNG penuh (1080x1500); pratinjau memakai skala kecil agar hemat memori.
 * Mengembalikan info tata letak (dipakai untuk pengujian).
 */
export function gambarKartu(canvas, data, skala = 1) {
    canvas.width = Math.round(W * skala);
    canvas.height = Math.round(H * skala);

    const ctx = canvas.getContext('2d');
    ctx.setTransform(skala, 0, 0, skala, 0, 0);

    const [latar, pasangan] = WARNA[data.warna] ?? WARNA.kuning;
    const M = 64;        // Tepi kiri/atas isi
    const CW = W - M * 2; // Lebar isi

    // Latar + titik tipis
    ctx.fillStyle = latar;
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = 'rgba(17,17,17,0.13)';

    for (let y = 20; y < H; y += 40) {
        for (let x = 20; x < W; x += 40) {
            ctx.beginPath();
            ctx.arc(x, y, 3, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    // Hiasan: satu lingkaran dan satu tanda plus
    ctx.beginPath();
    ctx.arc(1000, 1450, 110, 0, Math.PI * 2);
    ctx.fillStyle = '#fff';
    ctx.fill();
    ctx.lineWidth = 8;
    ctx.strokeStyle = INK;
    ctx.stroke();

    ctx.lineWidth = 12;
    ctx.beginPath();
    ctx.moveTo(M + 14, 1458);
    ctx.lineTo(M + 58, 1458);
    ctx.moveTo(M + 36, 1436);
    ctx.lineTo(M + 36, 1480);
    ctx.stroke();

    let y = M;
    const JARAK = 30;

    // --- judul + kotak kelas ---
    const tBanner = 104;
    ctx.fillStyle = INK;
    ctx.fillRect(M, y, CW, tBanner);
    teks(ctx, 'KODE MASUK', M + 28, y + 71, { font: F_JUDUL(68), warna: '#FFD93D', jarak: 1.4 });

    let pxKelas = 52;
    while (pxKelas > 28 && ukur(ctx, data.kelas, F_JUDUL(pxKelas)) > 330) pxKelas -= 2;

    let teksKelas = data.kelas;
    if (ukur(ctx, teksKelas, F_JUDUL(pxKelas)) > 330) {
        ctx.font = F_JUDUL(pxKelas);
        teksKelas = potongTitik(ctx, teksKelas, 330);
    }

    const lebarKelas = ukur(ctx, teksKelas, F_JUDUL(pxKelas)) + 48;
    const kX = M + CW - 22 - lebarKelas;

    ctx.fillStyle = '#fff';
    ctx.fillRect(kX - 6, y + 12, lebarKelas + 12, tBanner - 24);
    ctx.fillStyle = pasangan;
    ctx.fillRect(kX, y + 18, lebarKelas, tBanner - 36);
    teks(ctx, teksKelas, kX + lebarKelas / 2, y + 18 + (tBanner - 36) / 2 + pxKelas * 0.35, {
        font: F_JUDUL(pxKelas),
        rata: 'center',
    });

    y += tBanner + JARAK;

    // --- panel berlabel ---
    const LABEL = 50;
    const panel = (yy, tinggiIsi, warnaLabel, label, kanan) => {
        const tinggi = 7 + LABEL + 7 + tinggiIsi + 7;
        kotak(ctx, M, yy, CW, tinggi);

        ctx.fillStyle = warnaLabel;
        ctx.fillRect(M + 7, yy + 7, CW - 14, LABEL);
        ctx.fillStyle = INK;
        ctx.fillRect(M + 7, yy + 7 + LABEL, CW - 14, 7);

        teks(ctx, label, M + 7 + 22, yy + 7 + 35, { font: F_TEKS(28), jarak: 1.6 });
        if (kanan) teks(ctx, kanan, M + CW - 7 - 22, yy + 7 + 35, { font: F_TEKS(28), jarak: 1.6, rata: 'right' });

        return { atasIsi: yy + 7 + LABEL + 7, tinggi };
    };

    // 1. nama
    const tNama = 180;
    const pNama = panel(y, tNama, '#FFD93D', 'NAMA');
    const lebarNama = CW - 14 - 56;
    const cocok = cocokkanNama(ctx, data.nama, lebarNama);
    const tinggiBarisNama = cocok.px * 1.08;
    const blok = tinggiBarisNama * cocok.baris.length;

    cocok.baris.forEach((b, i) => {
        const base = pNama.atasIsi + (tNama - blok) / 2 + tinggiBarisNama * i + cocok.px * 0.86;
        teks(ctx, b, M + 7 + 28, base, { font: F_JUDUL(cocok.px) });
    });

    y += pNama.tinggi + JARAK;

    // 2. situs
    const tSitus = 104;
    const pSitus = panel(y, tSitus, '#6BCB77', '1 · BUKA SITUS');
    let pxSitus = 60;
    while (pxSitus > 28 && ukur(ctx, data.situs, F_KODE(pxSitus)) > CW - 14 - 56) pxSitus -= 2;

    teks(ctx, data.situs, M + 7 + 28, pSitus.atasIsi + tSitus / 2 + pxSitus * 0.32, { font: F_KODE(pxSitus) });
    y += pSitus.tinggi + JARAK;

    // 3 & 4. kode kelas dan kode pribadi
    const tKode = 170;
    const gambarKode = (label, warnaLabel, kode, kanan) => {
        const p = panel(y, tKode, warnaLabel, label, kanan);
        teks(ctx, kode, M + 7 + 28, p.atasIsi + tKode / 2 + 120 * 0.32, { font: F_KODE(120), jarak: 3 });
        y += p.tinggi + JARAK;
    };

    gambarKode('2 · KODE KELAS', '#4D96FF', data.kodeKelas);
    gambarKode('3 · KODE PRIBADI', '#FF6B9D', data.kodePribadi, 'RAHASIA');

    // --- catatan ---
    const px = 34;
    const tBaris = 44;
    const bagian = [
        ['Kode pribadi = password.', true],
        [' Jangan dibagikan. Hilang? Lapor ke guru. Di halaman kelas, pilih ', false],
        ['Masuk ke Dashboard Saya', true],
        ['.', false],
    ];
    const lebarTeks = CW - 14 - 56;

    // Hitung jumlah baris dulu untuk menentukan tinggi kotak
    const kanvasUkur = document.createElement('canvas').getContext('2d');
    const nBaris = paragraf(kanvasUkur, bagian, 0, 0, lebarTeks, tBaris, px);
    const tCatatan = 14 + 30 + nBaris * tBaris + 14;

    kotak(ctx, M, y, CW, tCatatan, { isi: '#FF4D4D' });
    paragraf(ctx, bagian, M + 7 + 28, y + 7 + 30 + 28, lebarTeks, tBaris, px);

    const dasar = y + tCatatan;

    // Bingkai luar
    ctx.strokeStyle = INK;
    ctx.lineWidth = 20;
    ctx.strokeRect(0, 0, W, H);

    return { namaPx: cocok.px, namaBaris: cocok.baris, dasar, tinggiTotal: H };
}

/* ---------- unduh ---------- */

function slug(s) {
    return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'siswa';
}

function namaBerkas(d) {
    return `kode-masuk-${slug(d.kelas)}-${slug(d.nama)}.png`;
}

function keBlob(canvas) {
    return new Promise((ok) => canvas.toBlob(ok, 'image/png'));
}

function simpan(blob, nama) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = nama;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
}

/* ---------- ZIP sederhana (tanpa kompresi) ---------- */

const TABEL_CRC = (() => {
    const t = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        t[n] = c >>> 0;
    }
    return t;
})();

export function crc32(data) {
    let c = 0xffffffff;
    for (let i = 0; i < data.length; i++) c = TABEL_CRC[(c ^ data[i]) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
}

export function buatZip(berkas) {
    const enc = new TextEncoder();
    const bagian = [];
    const pusat = [];
    let geser = 0;

    const d = new Date();
    const waktu = (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1);
    const tanggal = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();

    for (const f of berkas) {
        const nama = enc.encode(f.nama);
        const crc = crc32(f.data);
        const ukuran = f.data.length;

        const lokal = new DataView(new ArrayBuffer(30));
        lokal.setUint32(0, 0x04034b50, true);
        lokal.setUint16(4, 20, true);
        lokal.setUint16(6, 0x0800, true);
        lokal.setUint16(8, 0, true);
        lokal.setUint16(10, waktu, true);
        lokal.setUint16(12, tanggal, true);
        lokal.setUint32(14, crc, true);
        lokal.setUint32(18, ukuran, true);
        lokal.setUint32(22, ukuran, true);
        lokal.setUint16(26, nama.length, true);
        lokal.setUint16(28, 0, true);
        bagian.push(lokal.buffer, nama, f.data);

        const p = new DataView(new ArrayBuffer(46));
        p.setUint32(0, 0x02014b50, true);
        p.setUint16(4, 20, true);
        p.setUint16(6, 20, true);
        p.setUint16(8, 0x0800, true);
        p.setUint16(10, 0, true);
        p.setUint16(12, waktu, true);
        p.setUint16(14, tanggal, true);
        p.setUint32(16, crc, true);
        p.setUint32(20, ukuran, true);
        p.setUint32(24, ukuran, true);
        p.setUint16(28, nama.length, true);
        p.setUint32(42, geser, true);
        pusat.push(p.buffer, nama);

        geser += 30 + nama.length + ukuran;
    }

    const ukuranPusat = pusat.reduce((a, b) => a + (b.byteLength ?? b.length), 0);
    const akhir = new DataView(new ArrayBuffer(22));
    akhir.setUint32(0, 0x06054b50, true);
    akhir.setUint16(8, berkas.length, true);
    akhir.setUint16(10, berkas.length, true);
    akhir.setUint32(12, ukuranPusat, true);
    akhir.setUint32(16, geser, true);

    return new Blob([...bagian, ...pusat, akhir.buffer], { type: 'application/zip' });
}

/* ---------- halaman ---------- */

async function siapkanFont() {
    const daftar = [F_JUDUL(68), F_KODE(120), F_TEKS(28), F_BIASA(34)];
    try {
        await Promise.all(daftar.map((f) => document.fonts.load(f, 'Aa 0123')));
        await document.fonts.ready;
    } catch (e) {
        /* font cadangan dipakai */
    }
}

function mulai() {
    const halaman = document.querySelector('[data-kartu-halaman]');
    if (!halaman) return;

    const kanvas = [...halaman.querySelectorAll('canvas[data-kartu]')];
    const data = new Map(kanvas.map((c) => [c, JSON.parse(c.dataset.kartu)]));
    const SKALA_PRATINJAU = 0.36;

    const gambarSemua = () => kanvas.forEach((c) => gambarKartu(c, data.get(c), SKALA_PRATINJAU));
    siapkanFont().then(gambarSemua);

    // Kalau font baru selesai dimuat setelah gambar pertama, gambar ulang
    document.fonts?.addEventListener?.('loadingdone', gambarSemua);

    const penuh = async (d) => {
        const c = document.createElement('canvas');
        gambarKartu(c, d, 1);
        const blob = await keBlob(c);
        c.width = c.height = 0;
        return blob;
    };

    halaman.addEventListener('click', async (e) => {
        const tUnduh = e.target.closest('[data-unduh]');
        if (tUnduh) {
            const d = data.get(tUnduh.closest('div.space-y-2').querySelector('canvas'));
            await siapkanFont();
            simpan(await penuh(d), namaBerkas(d));
            return;
        }

        const tZip = e.target.closest('[data-zip]');
        if (tZip) {
            const status = halaman.querySelector('[data-status]');
            tZip.disabled = true;
            await siapkanFont();

            const berkas = [];
            const dipakai = new Set();
            let i = 0;

            for (const d of data.values()) {
                i++;
                status.textContent = `Menyiapkan ${i} dari ${data.size}…`;
                const buf = new Uint8Array(await (await penuh(d)).arrayBuffer());

                let nama = namaBerkas(d);
                for (let n = 2; dipakai.has(nama); n++) nama = namaBerkas(d).replace('.png', `-${n}.png`);
                dipakai.add(nama);

                berkas.push({ nama, data: buf });
                await new Promise((r) => setTimeout(r)); // Beri napas ke browser
            }

            const kelas = [...data.values()][0].kelas;
            simpan(buatZip(berkas), `kode-masuk-${slug(kelas)}.zip`);
            status.textContent = `Selesai: ${berkas.length} gambar.`;
            tZip.disabled = false;
        }
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
    else mulai();
}