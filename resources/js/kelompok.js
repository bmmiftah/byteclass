export function daftarKelompok(Alpine) {
    // (Helper ini sama dengan di denah.js; bisa digabung nanti saat perapian kode.)
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

    const warna = ['var(--kuning)', 'var(--biru)', 'var(--pink)', 'var(--hijau)', 'var(--oranye)'];

    /* ===================== EDITOR (guru) ===================== */
    Alpine.data('editorKelompok', (awal) => ({
        urls: awal.urls,
        sesi: awal.sesi,
        kelompoks: awal.kelompoks,
        siswas: awal.siswas,
        tema: awal.tema,
        pilih: null, // id siswa terpilih
        seret: null, // id siswa yang sedang diseret
        metode: 'acak',
        hindariUlang: true,
        acakKetua: true,
        temaPilih: 'warna',
        kotor: false,
        sibuk: false,
        sembunyi: false,
        muncul: new Set(),
        toast: '',
        toastError: false,

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
        warna(i) {
            return warna[i % warna.length];
        },

        get peta() {
            const o = {};
            this.siswas.forEach((s) => {
                o[s.id] = s;
            });
            return o;
        },

        namaSiswa(id) {
            return this.peta[id] ? this.peta[id].tampil : '?';
        },

        get dipakai() {
            const s = new Set();
            this.kelompoks.forEach((k) => k.anggota.forEach((a) => s.add(a.siswa_id)));
            return s;
        },

        get belum() {
            return this.siswas.filter((s) => !this.dipakai.has(s.id));
        },

        tersembunyi(id) {
            return this.sembunyi && !this.muncul.has(id);
        },

        beri(teks, galat = false) {
            this.toast = teks;
            this.toastError = galat;
            clearTimeout(this._t);
            this._t = setTimeout(() => (this.toast = ''), 2800);
        },

        /* ---- memilih & memindah siswa ---- */
        pilihSiswa(id) {
            this.pilih = this.pilih === id ? null : id;
        },

        posisi(id) {
            for (let i = 0; i < this.kelompoks.length; i++) {
                const j = this.kelompoks[i].anggota.findIndex((a) => a.siswa_id === id);
                if (j >= 0) return { i, j };
            }
            return null;
        },

        keluarkan(id) {
            const p = this.posisi(id);
            if (!p) return;
            const k = this.kelompoks[p.i];
            k.anggota.splice(p.j, 1);
            if (k.ketua === id) k.ketua = null;
            this.kotor = true;
        },

        pindah(id, i) {
            const p = this.posisi(id);
            if (p && p.i === i) return;
            this.keluarkan(id);
            this.kelompoks[i].anggota.push({ siswa_id: id, terkunci: false });
            this.kotor = true;
        },

        pindahKe(i) {
            if (this.pilih === null) return;
            this.pindah(this.pilih, i);
        },

        jatuhKe(i) {
            if (this.seret === null) return;
            this.pindah(this.seret, i);
            this.seret = null;
        },

        jatuhBelum() {
            if (this.seret === null) return;
            this.keluarkan(this.seret);
            this.seret = null;
        },

        get dataPilih() {
            if (this.pilih === null) return null;
            const p = this.posisi(this.pilih);
            return {
                p,
                anggota: p ? this.kelompoks[p.i].anggota[p.j] : null,
                ketua: p ? this.kelompoks[p.i].ketua === this.pilih : false,
            };
        },

        toggleKunci() {
            const d = this.dataPilih;
            if (d && d.anggota) {
                d.anggota.terkunci = !d.anggota.terkunci;
                this.kotor = true;
            }
        },

        jadikanKetua() {
            const d = this.dataPilih;
            if (d && d.p) {
                this.kelompoks[d.p.i].ketua = this.pilih;
                this.kotor = true;
            }
        },

        keluarkanPilih() {
            if (this.pilih !== null) {
                this.keluarkan(this.pilih);
                this.pilih = null;
            }
        },

        /* ---- mengelola kelompok ---- */
        tambahKelompok() {
            if (this.kelompoks.length >= 20) return;
            this.kelompoks.push({
                id: null,
                nama: 'Kelompok ' + (this.kelompoks.length + 1),
                ketua: null,
                anggota: [],
            });
            this.kotor = true;
        },

        hapusKelompok(i) {
            const k = this.kelompoks[i];
            if (k.anggota.length && !confirm('Anggota kelompok ini akan kembali ke daftar "Belum dapat kelompok". Lanjutkan?')) {
                return;
            }
            this.kelompoks.splice(i, 1);
            this.pilih = null;
            this.kotor = true;
        },

        namaOtomatis() {
            const daftar = this.tema[this.temaPilih];
            this.kelompoks.forEach((k, i) => {
                k.nama = daftar && daftar[i] ? daftar[i] : 'Kelompok ' + (i + 1);
            });
            this.kotor = true;
        },

        /* ---- simpan & acak ---- */
        terapkan(data) {
            this.sesi = data.sesi;
            this.kelompoks = data.kelompoks;
            this.pilih = null;
        },

        async simpanState() {
            const r = await kirim('PUT', this.urls.state, {
                nama: this.sesi.nama,
                nama_lengkap: this.sesi.nama_lengkap,
                kelompoks: this.kelompoks.map((k) => ({
                    id: k.id,
                    nama: k.nama,
                    ketua: k.ketua,
                    anggota: k.anggota,
                })),
            });
            this.terapkan(await hasilJson(r));
            this.kotor = false;
        },

        async simpan() {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                await this.simpanState();
                this.beri('Tersimpan.');
            } catch (e) {
                this.beri(e.message, true);
            }
            this.sibuk = false;
        },

        async acak() {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                await this.simpanState();
                const r = await kirim('POST', this.urls.acak, {
                    metode: this.metode,
                    hindari_ulang: this.hindariUlang,
                    acak_ketua: this.acakKetua,
                });
                this.terapkan(await hasilJson(r));
                await this.animasi();
                this.beri('Selesai dibagi dan tersimpan.');
            } catch (e) {
                this.beri(e.message, true);
            }
            this.sembunyi = false;
            this.sibuk = false;
        },

        // Anggota "muncul" satu per satu setelah hasil diterima
        animasi() {
            return new Promise((selesai) => {
                const semua = this.kelompoks.flatMap((k) => k.anggota.map((a) => a.siswa_id));
                const urutan = [...semua].sort(() => Math.random() - 0.5);
                const jeda = Math.min(90, 2200 / Math.max(1, urutan.length));
                this.muncul = new Set();
                this.sembunyi = true;

                urutan.forEach((id, n) => {
                    setTimeout(() => {
                        const baru = new Set(this.muncul);
                        baru.add(id);
                        this.muncul = baru;
                    }, 200 + n * jeda);
                });

                setTimeout(selesai, 200 + urutan.length * jeda + 200);
            });
        },

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
    Alpine.data('tampilKelompok', (awal) => ({
        sesi: awal.sesi,
        kelompoks: awal.kelompoks,
        tahap: 0,

        init() {
            window.addEventListener('beforeprint', () => {
                this.tahap = this.total;
            });

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.tahap = this.total;
            } else {
                this.putar();
            }
        },

        warna(i) {
            return warna[i % warna.length];
        },

        get total() {
            return this.kelompoks.reduce((j, k) => j + k.anggota.length, 0);
        },

        // Beri nomor urut global pada tiap anggota untuk animasi
        get daftar() {
            let n = 0;
            return this.kelompoks.map((k) => ({
                ...k,
                anggota: k.anggota.map((nama) => ({ nama, n: n++ })),
            }));
        },

        putar() {
            clearInterval(this._i);
            this.tahap = 0;
            const jeda = Math.min(120, 3000 / Math.max(1, this.total));

            this._i = setInterval(() => {
                this.tahap++;
                if (this.tahap >= this.total) clearInterval(this._i);
            }, jeda);
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