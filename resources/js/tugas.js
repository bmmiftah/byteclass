// Fase 8B: bantu siswa mengunggah tugas dari HP.
// - foto besar diperkecil dulu di HP (hemat kuota & penyimpanan)
// - daftar file terpilih bisa ditambah satu per satu dan dihapus
// - cek jumlah, ukuran, dan jenis file sebelum dikirim
// Kalau fitur browser tidak ada, form tetap jalan biasa (server tetap memeriksa semuanya).

const BATAS_KECIL = 1.5 * 1024 * 1024; // Foto di bawah ini tidak diutak-atik
const LEBAR_MAKS = 1600;
const KUALITAS = 0.82;

const ukuranTeks = (b) => (
    b < 1048576
        ? Math.max(1, Math.round(b / 1024)) + ' KB'
        : (b / 1048576).toFixed(1) + ' MB'
);

function bacaGambar(file) {
    if (window.createImageBitmap) {
        return createImageBitmap(file, { imageOrientation: 'from-image' })
            .catch(() => createImageBitmap(file));
    }

    return new Promise((ok, gagal) => {
        const url = URL.createObjectURL(file);
        const im = new Image();

        im.onload = () => {
            URL.revokeObjectURL(url);
            ok(im);
        };

        im.onerror = () => {
            URL.revokeObjectURL(url);
            gagal(new Error('gambar rusak'));
        };

        im.src = url;
    });
}

async function perkecil(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size <= BATAS_KECIL) {
        return file;
    }

    try {
        const gbr = await bacaGambar(file);
        const w0 = gbr.width;
        const h0 = gbr.height;
        const skala = Math.min(1, LEBAR_MAKS / Math.max(w0, h0));

        const kanvas = document.createElement('canvas');
        kanvas.width = Math.max(1, Math.round(w0 * skala));
        kanvas.height = Math.max(1, Math.round(h0 * skala));

        const g = kanvas.getContext('2d');
        g.fillStyle = '#fff';
        g.fillRect(0, 0, kanvas.width, kanvas.height);
        g.drawImage(gbr, 0, 0, kanvas.width, kanvas.height);

        if (gbr.close) {
            gbr.close();
        }

        const blob = await new Promise((ok) => kanvas.toBlob(ok, 'image/jpeg', KUALITAS));
        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File(
            [blob],
            file.name.replace(/\.[^.]+$/, '') + '.jpg',
            { type: 'image/jpeg', lastModified: Date.now() }
        );
    } catch (e) {
        return file; // Gagal memperkecil: kirim yang asli
    }
}

function pasang(form) {
    const input = form.querySelector('input[type=file][data-tugas-berkas]');
    if (!input) return;

    try {
        new DataTransfer();
    } catch (e) {
        return; // Browser lama: pakai cara biasa
    }

    const maks = parseInt(input.dataset.maks || '3', 10);
    const mb = parseInt(input.dataset.mb || '10', 10);
    const ekst = (input.dataset.ekstensi || '')
        .split(',')
        .map((s) => s.trim().toLowerCase())
        .filter(Boolean);

    const daftar = form.querySelector('[data-tugas-daftar]');
    const pesan = form.querySelector('[data-tugas-pesan]');
    const tombol = form.querySelector('[data-tugas-kirim]');

    let terpilih = [];

    const tampil = (teks) => {
        if (pesan) {
            pesan.textContent = teks || '';
            pesan.hidden = !teks;
        }
    };

    const sinkron = () => {
        const dt = new DataTransfer();
        terpilih.forEach((f) => dt.items.add(f));
        input.files = dt.files;

        if (!daftar) return;
        daftar.innerHTML = '';

        terpilih.forEach((f, i) => {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between gap-2 border-2 border-black bg-white px-2 py-1';

            const nama = document.createElement('span');
            nama.className = 'truncate';
            nama.textContent = f.name + ' (' + ukuranTeks(f.size) + ')';

            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'nb-btn putih';
            x.textContent = 'Hapus';
            x.addEventListener('click', () => {
                terpilih.splice(i, 1);
                sinkron();
                tampil('');
            });

            li.append(nama, x);
            daftar.append(li);
        });
    };

    input.addEventListener('change', async () => {
        const baru = Array.from(input.files);
        const masalah = [];

        tampil('Memproses...');

        for (const f of baru) {
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            if (ekst.length && !ekst.includes(ext)) {
                masalah.push(f.name + ': jenis file tidak diizinkan');
                continue;
            }

            if (terpilih.length >= maks) {
                masalah.push('Maksimal ' + maks + ' file');
                break;
            }

            const kecil = await perkecil(f);
            if (kecil.size > mb * 1048576) {
                masalah.push(f.name + ': lebih dari ' + mb + ' MB');
                continue;
            }

            if (terpilih.some((t) => t.name === kecil.name && t.size === kecil.size)) {
                continue;
            }

            terpilih.push(kecil);
        }

        sinkron();
        tampil(masalah.join('. '));
    });

    form.addEventListener('submit', () => {
        if (tombol) {
            tombol.disabled = true;
            tombol.textContent = 'Mengunggah... jangan tutup halaman';
        }
    });
}

document.querySelectorAll('form[data-tugas-form]').forEach(pasang);