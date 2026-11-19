import { kirim, hasilJson } from './bantu';

// Skala 0-100 per kriteria (sama dengan rumus di server)
const normal = (k, v) => {
    if (k.tipe === 'ceklis') return v > 0 ? 100 : 0;
    if (!(k.maks > 0)) return 0;
    return Math.max(0, Math.min(v, k.maks)) / k.maks * 100;
};

export function daftarPenilaian(Alpine) {
    /* ===================== EDITOR RUBRIK ===================== */

    Alpine.data('editorRubrik', (awal) => ({
        url: awal.url,
        nama: awal.nama,
        kriterias: awal.kriterias,
        kotor: false,
        sibuk: false,
        toast: '',
        toastError: false,

        init() {
            window.addEventListener('beforeunload', (e) => {
                if (this.kotor) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        beri(teks, galat = false) {
            this.toast = teks;
            this.toastError = galat;
            clearTimeout(this._t);
            this._t = setTimeout(() => (this.toast = ''), 2800);
        },

        get totalBobot() {
            return this.kriterias.reduce((j, k) => j + (Number(k.bobot) || 0), 0);
        },

        persen(k) {
            return this.totalBobot ? Math.round((Number(k.bobot) || 0) / this.totalBobot * 100) : 0;
        },

        tambah() {
            this.kriterias.push({ nama: '', tipe: 'angka', maks: 100, bobot: 1 });
            this.kotor = true;
        },

        hapus(i) {
            if (this.kriterias.length <= 1) {
                this.beri('Minimal satu kriteria.', true);
                return;
            }

            this.kriterias.splice(i, 1);
            this.kotor = true;
        },

        naik(i) {
            if (i < 1) return;
            const [k] = this.kriterias.splice(i, 1);
            this.kriterias.splice(i - 1, 0, k);
            this.kotor = true;
        },

        turun(i) {
            if (i >= this.kriterias.length - 1) return;
            const [k] = this.kriterias.splice(i, 1);
            this.kriterias.splice(i + 1, 0, k);
            this.kotor = true;
        },

        ubahTipe(k) {
            k.maks = k.tipe === 'ceklis' ? 1 : (k.tipe === 'poin' ? 10 : 100);
            this.kotor = true;
        },

        async simpan() {
            if (this.sibuk) return;

            if (this.kriterias.some((k) => !k.nama.trim())) {
                this.beri('Nama kriteria tidak boleh kosong.', true);
                return;
            }

            this.sibuk = true;

            try {
                const r = await kirim('PUT', this.url, { nama: this.nama, kriterias: this.kriterias });
                const d = await hasilJson(r);
                this.nama = d.nama;
                this.kriterias = d.kriterias;
                this.kotor = false;
                this.beri('Rubrik tersimpan.');
            } catch (e) {
                this.beri(e.message, true);
            }

            this.sibuk = false;
        },
    }));

    /* ===================== NILAI CEPAT ===================== */

    Alpine.data('nilaiCepat', (awal) => ({
        urls: awal.urls,
        penilaian: awal.penilaian,
        kriteria: awal.kriteria,
        siswas: awal.siswas,
        kelompoks: awal.kelompoks,
        nilai: awal.nilai, // id_siswa -> {total, catatan, detail}
        mode: awal.penilaian.jenis === 'kelompok' ? 'kelompok' : 'siswa',
        i: 0, // indeks unit yang sedang dibuka
        cur: { detail: {}, catatan: '' }, // isian unit yang sedang dibuka
        daftarBuka: false,
        kotor: false, // ada perubahan belum masuk antrean
        status: 'tersimpan', // tersimpan | menyimpan | menunggu
        antrian: {}, // kunci unit -> muatan yang belum terkirim
        sedangKirim: false,
        toast: '',
        toastError: false,

        init() {
            try {
                this.antrian = JSON.parse(localStorage.getItem(this.kunciSimpan) || '{}') || {};
            } catch (e) {
                this.antrian = {};
            }

            Object.values(this.antrian).forEach((p) => this.terapkanLokal(p));
            this.bukaUnit(0);

            window.addEventListener('online', () => this.kirimAntrian());
            window.addEventListener('beforeunload', (e) => {
                if (this.kotor || Object.keys(this.antrian).length) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            setInterval(() => this.kirimAntrian(), 8000);
            this.kirimAntrian();
        },

        get kunciSimpan() {
            return 'antrian-nilai-' + this.penilaian.id;
        },

        /* ---- unit yang dinilai ---- */

        namaPendek(id) {
            const s = this.siswas.find((x) => x.id === id);
            return s ? s.tampil : '?';
        },

        get units() {
            if (this.mode === 'kelompok') {
                const dipakai = new Set();
                const u = this.kelompoks.map((k) => {
                    k.anggota.forEach((id) => dipakai.add(id));
                    return {
                        kunci: 'k' + k.id,
                        nama: k.nama,
                        sub: k.anggota.map((id) => this.namaPendek(id)).join(', '),
                        ids: k.anggota,
                    };
                });

                this.siswas.filter((s) => !dipakai.has(s.id)).forEach((s) => {
                    u.push({ kunci: 's' + s.id, nama: s.nama, sub: 'Tanpa kelompok', ids: [s.id] });
                });

                return u;
            }

            return this.siswas.map((s) => ({ kunci: 's' + s.id, nama: s.nama, sub: '', ids: [s.id] }));
        },

        get unit() {
            return this.units[this.i];
        },

        dataUnit(u) {
            const id = u.ids.find((x) => this.nilai[x]);
            return id ? this.nilai[id] : null;
        },

        statusUnit(u) {
            const n = this.dataUnit(u);
            if (!n) return 'belum';
            return Object.keys(n.detail || {}).length >= this.kriteria.length ? 'lengkap' : 'sebagian';
        },

        warnaStatus(s) {
            return s === 'lengkap' ? 'var(--hijau)' : (s === 'sebagian' ? 'var(--kuning)' : '#fff');
        },

        infoUnit(u) {
            const n = this.dataUnit(u);
            return n && n.total !== null ? 'Nilai ' + n.total : 'Belum dinilai';
        },

        get selesai() {
            return this.units.filter((u) => this.statusUnit(u) === 'lengkap').length;
        },

        /* ---- membuka & berpindah unit ---- */

        bukaUnit(n) {
            const total = this.units.length;
            if (!total) return;

            this.i = Math.max(0, Math.min(total - 1, n));
            const data = this.dataUnit(this.unit);
            const detail = {};

            this.kriteria.forEach((k) => {
                const v = data && data.detail ? data.detail[k.id] : undefined;
                detail[k.id] = v !== undefined && v !== null ? v : (k.tipe === 'ceklis' ? 0 : null);
            });

            this.cur = { detail, catatan: data && data.catatan ? data.catatan : '' };
            this.kotor = false;
        },

        pindah(delta) {
            this.simpanUnit();
            this.bukaUnit(this.i + delta);
        },

        lompat(n) {
            this.simpanUnit();
            this.daftarBuka = false;
            this.bukaUnit(n);
        },

        gantiMode(m) {
            this.simpanUnit();
            this.mode = m;
            this.bukaUnit(0);
        },

        // geser kiri/kanan di layar sentuh
        sentuhMulai(e) {
            this.sx = e.changedTouches[0].clientX;
            this.sy = e.changedTouches[0].clientY;
        },

        sentuhSelesai(e) {
            const dx = e.changedTouches[0].clientX - this.sx;
            const dy = e.changedTouches[0].clientY - this.sy;
            if (Math.abs(dx) > 80 && Math.abs(dx) > Math.abs(dy) * 1.5) this.pindah(dx < 0 ? 1 : -1);
        },

        /* ---- mengisi nilai ---- */

        rentang(maks) {
            return Array.from({ length: maks }, (_, n) => n + 1);
        },

        set(idKriteria, nilai) {
            const k = this.kriteria.find((x) => x.id === idKriteria);

            if (k.tipe !== 'ceklis' && this.cur.detail[idKriteria] === nilai) {
                this.cur.detail[idKriteria] = null; // ketuk lagi = batalkan pilihan
            } else {
                this.cur.detail[idKriteria] = nilai;
            }

            this.ubah();
        },

        geser(idKriteria, langkah) {
            const k = this.kriteria.find((x) => x.id === idKriteria);
            const sekarang = Number(this.cur.detail[idKriteria]) || 0;
            this.cur.detail[idKriteria] = Math.max(0, Math.min(k.maks, sekarang + langkah));
            this.ubah();
        },

        ubah() {
            this.kotor = true;
            this.status = 'menyimpan';
            clearTimeout(this._d);
            this._d = setTimeout(() => this.simpanUnit(), 1200);
        },

        hitung(detail) {
            let bobot = 0;
            let skor = 0;

            this.kriteria.forEach((k) => {
                bobot += k.bobot;
                skor += normal(k, Number(detail[k.id]) || 0) * k.bobot;
            });

            return bobot ? Math.round(skor / bobot * 100) / 100 : 0;
        },

        get totalLive() {
            return this.hitung(this.cur.detail);
        },

        get tuntas() {
            return this.totalLive >= this.penilaian.kkm;
        },

        /* ---- menyimpan (otomatis, tahan sinyal buruk) ---- */

        bersihkan(detail) {
            const o = {};

            this.kriteria.forEach((k) => {
                let v = detail[k.id];
                if (v === '' || v === undefined) v = null;
                if (v !== null) {
                    v = Number(v);
                    if (Number.isNaN(v)) v = null;
                }
                o[k.id] = v;
            });

            return o;
        },

        terapkanLokal(p) {
            const detail = {};
            Object.entries(p.detail).forEach(([id, v]) => {
                if (v !== null) detail[id] = v;
            });

            p.siswa_ids.forEach((sid) => {
                this.nilai[sid] = { total: p.total, catatan: p.catatan, detail: { ...detail } };
            });
        },

        simpanLokal() {
            try {
                localStorage.setItem(this.kunciSimpan, JSON.stringify(this.antrian));
            } catch (e) {
                /* abaikan */
            }
        },

        simpanUnit() {
            clearTimeout(this._d);
            const u = this.unit;
            if (!this.kotor || !u || !u.ids.length) return;

            const detail = this.bersihkan(this.cur.detail);
            const muatan = {
                siswa_ids: u.ids,
                detail,
                catatan: this.cur.catatan || '',
                total: this.hitung(detail),
            };

            this.kotor = false;
            this.antrian[u.kunci] = muatan;
            this.simpanLokal();
            this.terapkanLokal(muatan);
            this.kirimAntrian();
        },

        async kirimAntrian() {
            if (this.sedangKirim) return;

            const kunci = Object.keys(this.antrian);
            if (!kunci.length) {
                if (!this.kotor) this.status = 'tersimpan';
                return;
            }

            this.sedangKirim = true;
            this.status = 'menyimpan';

            for (const k of kunci) {
                const p = this.antrian[k];

                try {
                    const r = await kirim('PUT', this.urls.simpan, p);

                    if (r.status === 422) {
                        delete this.antrian[k];
                        this.beri('Data ditolak server.', true);
                        continue;
                    }

                    if (!r.ok) throw new Error('gagal');

                    const d = await r.json();
                    p.siswa_ids.forEach((id) => {
                        if (this.nilai[id]) this.nilai[id].total = d.total;
                    });

                    if (this.antrian[k] === p) delete this.antrian[k];
                } catch (e) {
                    this.status = 'menunggu';
                    break;
                }
            }

            this.simpanLokal();
            this.sedangKirim = false;

            if (!Object.keys(this.antrian).length && !this.kotor) this.status = 'tersimpan';
        },

        async simpanLanjut() {
            this.simpanUnit();

            if (this.i < this.units.length - 1) this.bukaUnit(this.i + 1);
            else this.beri('Ini unit terakhir. Semua tersimpan.');
        },

        beri(teks, galat = false) {
            this.toast = teks;
            this.toastError = galat;
            clearTimeout(this._t);
            this._t = setTimeout(() => (this.toast = ''), 2800);
        },
    }));
}