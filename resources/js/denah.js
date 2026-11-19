export function daftarDenah(Alpine) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

    const kirim = (metode, url, body) =>
        fetch(url, {
            method: metode,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: body ? JSON.stringify(body) : undefined,
        });

    const hasilJson = async (r) => {
        let d = {};
        try {
            d = await r.json();
        } catch (e) {
            /* kosong */
        }
        if (!r.ok) {
            throw new Error(d.pesan || d.message || 'Terjadi kesalahan (' + r.status + ')');
        }
        return d;
    };

    const gayaKanvas = (d) =>
        `--kolom:${d.lebar};--baris:${d.tinggi};aspect-ratio:${d.lebar}/${d.tinggi};` +
        `min-width:${d.lebar * 56}px;max-width:${d.lebar * 96}px`;

    const posisiCss = (d, x, y) => ({
        left: (x / d.lebar * 100) + '%',
        top: (y / d.tinggi * 100) + '%',
        width: (100 / d.lebar) + '%',
        height: (100 / d.tinggi) + '%',
    });

    /* ===================== EDITOR (guru) ===================== */
    Alpine.data('editorDenah', (awal) => ({
        urls: awal.urls,
        denah: awal.denah,
        mejas: awal.mejas,
        siswas: awal.siswas,
        aturan: awal.aturan,
        riwayat: awal.riwayat,
        pilih: null, // indeks meja yang dipilih
        mode: 'pilih', // 'pilih' atau 'tambah'
        geser: null, // data geseran yang sedang berjalan
        kotor: false, // ada perubahan belum disimpan
        sibuk: false,
        hindariSama: true,
        putar: new Set(), // id meja yang sedang "berputar" (animasi)
        tampilAcak: {}, // id meja -> id siswa acak sementara
        toast: '',
        toastError: false,
        aturanA: '',
        aturanB: '',

        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.kotor) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            this.$nextTick(() => {
                if (window.QRCode && this.$refs.qr) {
                    window.QRCode.toCanvas(this.$refs.qr, this.urls.publik, { width: 176, margin: 1 });
                }
            });
        },

        /* ---- tampilan ---- */
        get gayaKanvas() {
            return gayaKanvas(this.denah);
        },

        gayaMeja(m) {
            return posisiCss(this.denah, m.x, m.y);
        },

        get peta() {
            const o = {};
            this.siswas.forEach((s) => {
                o[s.id] = s;
            });
            return o;
        },

        namaMeja(m) {
            const id = this.putar.has(m.id) ? this.tampilAcak[m.id] : m.siswa_id;
            return id && this.peta[id] ? this.peta[id].tampil : '';
        },

        namaSiswa(id) {
            return this.peta[id] ? this.peta[id].nama : '?';
        },

        get kurang() {
            return this.mejas.length < this.siswas.length;
        },

        beri(teks, galat = false) {
            this.toast = teks;
            this.toastError = galat;
            clearTimeout(this._t);
            this._t = setTimeout(() => (this.toast = ''), 2800);
        },

        /* ---- menambah, memilih, menggeser meja ---- */
        sel(e) {
            const r = this.$refs.kanvas.getBoundingClientRect();
            const x = Math.floor((e.clientX - r.left) / (r.width / this.denah.lebar));
            const y = Math.floor((e.clientY - r.top) / (r.height / this.denah.tinggi));
            return [
                Math.max(0, Math.min(this.denah.lebar - 1, x)),
                Math.max(0, Math.min(this.denah.tinggi - 1, y)),
            ];
        },

        ada(x, y, kecuali = -1) {
            return this.mejas.some((m, i) => i !== kecuali && m.x === x && m.y === y);
        },

        klikKanvas(e) {
            if (this.mode !== 'tambah') {
                this.pilih = null;
                return;
            }
            const [x, y] = this.sel(e);
            if (this.ada(x, y)) return;
            this.mejas.push({ id: null, x, y, terkunci: false, siswa_id: null });
            this.kotor = true;
        },

        mulaiGeser(e, i) {
            if (this.sibuk || this.mode !== 'pilih') return;
            this.pilih = i;
            e.currentTarget.setPointerCapture(e.pointerId);
            this.geser = { i, sx: e.clientX, sy: e.clientY, jalan: false };
        },

        geserBergerak(e) {
            const g = this.geser;
            if (!g) return;
            if (!g.jalan && Math.hypot(e.clientX - g.sx, e.clientY - g.sy) < 6) return;
            g.jalan = true;
            const [x, y] = this.sel(e);
            if (!this.ada(x, y, g.i)) {
                this.mejas[g.i].x = x;
                this.mejas[g.i].y = y;
                this.kotor = true;
            }
        },

        selesaiGeser() {
            this.geser = null;
        },

        /* ---- meja terpilih ---- */
        get mejaPilih() {
            return this.pilih !== null ? this.mejas[this.pilih] : null;
        },

        hapusPilih() {
            if (this.pilih === null) return;
            this.mejas.splice(this.pilih, 1);
            this.pilih = null;
            this.kotor = true;
        },

        toggleKunci() {
            if (!this.mejaPilih) return;
            this.mejaPilih.terkunci = !this.mejaPilih.terkunci;
            this.kotor = true;
        },

        setSiswa(nilai) {
            if (!this.mejaPilih) return;
            const id = nilai ? Number(nilai) : null;
            if (id) {
                this.mejas.forEach((m) => {
                    if (m.siswa_id === id) m.siswa_id = null;
                });
            }
            this.mejaPilih.siswa_id = id;
            this.kotor = true;
        },

        ubahUkuran() {
            const d = this.denah;
            d.lebar = Math.max(3, Math.min(16, Number(d.lebar) || 3));
            d.tinggi = Math.max(3, Math.min(12, Number(d.tinggi) || 3));
            const luar = this.mejas.filter((m) => m.x >= d.lebar || m.y >= d.tinggi).length;
            if (luar && !confirm(luar + ' meja berada di luar ukuran baru dan akan dihapus. Lanjutkan?')) {
                d.lebar = Math.max(d.lebar, ...this.mejas.map((m) => m.x + 1));
                d.tinggi = Math.max(d.tinggi, ...this.mejas.map((m) => m.y + 1));
                return;
            }
            this.mejas = this.mejas.filter((m) => m.x < d.lebar && m.y < d.tinggi);
            this.pilih = null;
            this.kotor = true;
        },

        /* ---- simpan & acak ---- */
        terapkan(data) {
            this.denah = data.denah;
            this.mejas = data.mejas;
            this.aturan = data.aturan;
            this.riwayat = data.riwayat;
            this.pilih = null;
        },

        async simpanLayout() {
            const r = await kirim('PUT', this.urls.layout, {
                nama: this.denah.nama,
                lebar: this.denah.lebar,
                tinggi: this.denah.tinggi,
                nama_lengkap: this.denah.nama_lengkap,
                mejas: this.mejas,
            });
            this.terapkan(await hasilJson(r));
            this.kotor = false;
        },

        async simpan() {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                await this.simpanLayout();
                this.beri('Layout tersimpan.');
            } catch (e) {
                this.beri(e.message, true);
            }
            this.sibuk = false;
        },

        async acak() {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                await this.simpanLayout();
                const r = await kirim('POST', this.urls.acak, { hindari_sama: this.hindariSama });
                const data = await hasilJson(r);
                await this.animasi(data.mejas);
                this.terapkan(data);
                this.beri('Selesai diacak dan tersimpan.');
            } catch (e) {
                this.beri(e.message, true);
            }
            this.putar = new Set();
            this.sibuk = false;
        },

        animasi(hasil) {
            return new Promise((selesai) => {
                const idSiswa = this.siswas.map((s) => s.id);
                const bebas = this.mejas.filter((m) => !m.terkunci);
                const akhir = new Map(hasil.map((m) => [m.id, m.siswa_id]));
                this.putar = new Set(bebas.map((m) => m.id));

                const putaran = setInterval(() => {
                    bebas.forEach((m) => {
                        if (this.putar.has(m.id)) {
                            this.tampilAcak[m.id] = idSiswa[Math.floor(Math.random() * idSiswa.length)];
                        }
                    });
                }, 70);

                const urutan = [...bebas].sort(() => Math.random() - 0.5);
                const jeda = Math.min(120, 2500 / Math.max(1, urutan.length));

                urutan.forEach((m, n) => {
                    setTimeout(() => {
                        m.siswa_id = akhir.get(m.id) ?? null;
                        const baru = new Set(this.putar);
                        baru.delete(m.id);
                        this.putar = baru;
                    }, 700 + n * jeda);
                });

                setTimeout(() => {
                    clearInterval(putaran);
                    selesai();
                }, 700 + urutan.length * jeda + 300);
            });
        },

        /* ---- aturan pisah ---- */
        async tambahAturan() {
            if (!this.aturanA || !this.aturanB) return;
            try {
                const r = await kirim('POST', this.urls.aturan, {
                    siswa_a: Number(this.aturanA),
                    siswa_b: Number(this.aturanB),
                });
                this.aturan = (await hasilJson(r)).aturan;
                this.aturanA = '';
                this.aturanB = '';
            } catch (e) {
                this.beri(e.message, true);
            }
        },

        async hapusAturan(id) {
            try {
                const r = await kirim('DELETE', this.urls.aturanHapus.replace('__ID__', id));
                this.aturan = (await hasilJson(r)).aturan;
            } catch (e) {
                this.beri(e.message, true);
            }
        },

        /* ---- riwayat ---- */
        async pulihkan(id) {
            if (this.kotor && !confirm('Ada perubahan layout yang belum disimpan. Simpan dulu sebelum memulihkan.')) {
                return;
            }
            try {
                if (this.kotor) await this.simpanLayout();
                const r = await kirim('POST', this.urls.pulihkan.replace('__ID__', id));
                this.terapkan(await hasilJson(r));
                this.beri('Susunan dipulihkan.');
            } catch (e) {
                this.beri(e.message, true);
            }
        },

        /* ---- bagikan ---- */
        async salin() {
            try {
                await navigator.clipboard.writeText(this.urls.publik);
                this.beri('Link disalin.');
            } catch (e) {
                this.beri('Salin manual: ' + this.urls.publik, true);
            }
        },
    }));

    /* ===================== TAMPILAN (publik / proyektor) ===================== */
    Alpine.data('tampilDenah', (awal) => ({
        denah: awal.denah,
        mejas: awal.mejas,
        siswa: awal.siswa,
        balik: false,

        get gayaKanvas() {
            return gayaKanvas(this.denah);
        },

        get tampil() {
            const d = this.denah;
            return this.mejas.map((m) => ({
                ...m,
                px: this.balik ? d.lebar - 1 - m.x : m.x,
                py: this.balik ? d.tinggi - 1 - m.y : m.y,
            }));
        },

        gaya(m, i) {
            return { ...posisiCss(this.denah, m.px, m.py), animationDelay: (i * 25) + 'ms' };
        },

        nama(m) {
            return m.siswa_id ? (this.siswa[m.siswa_id] || '') : '';
        },

        layarPenuh() {
            const el = this.$refs.area;
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (el.requestFullscreen) {
                el.requestFullscreen();
            } else if (el.webkitRequestFullscreen) {
                el.webkitRequestFullscreen();
            }
        },
    }));
}