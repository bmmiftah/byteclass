<x-cbt-layout :judul="$ujian->judul">
    <style>
        .tanpa-salin { -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
        .tanpa-salin input, .tanpa-salin textarea { -webkit-user-select: text; user-select: text; }
        @media print { body * { display: none !important; } }
    </style>
    @verbatim
    <script>
    window.ujianSiswa = (cfg) => ({
        soals: cfg.soals,
        idx: 0,
        sisa: cfg.sisa,
        akhirAt: Date.now() + cfg.sisa * 1000,
        tertunda: {},
        timers: {},
        status: 'ok',          // ok | tertunda
        mengirim: false,
        selesai: false,
        henti: '',
        peringatan: false,

        // Fase 9E
        blokir: cfg.blokirSalin,
        batas: cfg.batas,
        kumpulOto: cfg.kumpulOtomatis,
        pelanggaran: cfg.pelanggaran,
        sisaKesempatan: cfg.sisaKesempatan,
        toast: '',
        perluLayarPenuh: false,
        pernahLayarPenuh: false,
        pergi: null,
        keluarTertunda: false,
        tundaKeluar: null,
        menutup: false,
        abaikanSampai: 0,
        terakhir: {},
        antrean: [],

        init() {
            // pulihkan jawaban yang belum terkirim (mis. halaman dimuat ulang saat offline)
            try {
                const s = JSON.parse(localStorage.getItem(cfg.penyimpanan) || '{}');
                Object.entries(s).forEach(([id, v]) => {
                    const so = this.soals.find(x => String(x.id) === String(id));
                    if (so) { so.j = v.jawaban ?? ''; so.r = !!v.ragu; this.tertunda[id] = v; }
                });
            } catch (e) { /* abaikan */ }

            this.kirimTertunda();
            setInterval(() => this.tik(), 500);
            setInterval(() => { this.kirimTertunda(); this.kirimAntrean(); }, 5000);

            this.perluLayarPenuh = !!cfg.layarPenuh && this.layarPenuhDidukung;
            this.pasangPengawas();
        },

        get sekarang() { return this.soals[this.idx]; },
        get tampil() {
            const m = Math.floor(this.sisa / 60), d = this.sisa % 60;
            return String(m).padStart(2, '0') + ':' + String(d).padStart(2, '0');
        },
        get jumlahTerisi() { return this.soals.filter(s => (s.j ?? '') !== '').length; },
        get jumlahTertunda() { return Object.keys(this.tertunda).length; },

        tik() {
            if (this.selesai) return;
            this.sisa = Math.max(0, Math.round((this.akhirAt - Date.now()) / 1000));
            if (this.sisa === 0) this.kumpulkan(true);
        },

        ke(i) { this.idx = Math.max(0, Math.min(this.soals.length - 1, i)); window.scrollTo({ top: 0, behavior: 'smooth' }); },

        pilih(s, huruf) { s.j = huruf; this.catat(s); },
        ketik(s) {
            clearTimeout(this.timers[s.id]);
            this.timers[s.id] = setTimeout(() => this.catat(s), 700);
        },
        ragu(s) { s.r = !s.r; this.catat(s); },

        catat(s) {
            this.tertunda[s.id] = { jawaban: s.j, ragu: s.r };
            this.simpanLokal();
            this.kirimTertunda();
        },
        simpanLokal() {
            try { localStorage.setItem(cfg.penyimpanan, JSON.stringify(this.tertunda)); } catch (e) { /* abaikan */ }
        },
        header() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            };
        },

        async kirimTertunda() {
            if (this.mengirim || this.henti) return;
            const ids = Object.keys(this.tertunda);
            if (!ids.length) { this.status = 'ok'; return; }

            this.mengirim = true;
            try {
                for (const id of ids) {
                    const v = this.tertunda[id];
                    const r = await fetch(cfg.urlJawab, {
                        method: 'POST', headers: this.header(),
                        body: JSON.stringify({ soal_id: Number(id), jawaban: v.jawaban, ragu: v.ragu }),
                    });
                    if (r.status === 409) {
                        const d = await r.json();
                        this.henti = d.pesan; this.selesai = true; return;
                    }
                    if (r.status === 410) { location.href = cfg.urlHasil; return; }
                    if (r.status === 423) { location.reload(); return; }   // dikunci guru: tampilkan layar terkunci
                    if (r.status === 401) { location.href = cfg.urlMasuk; return; }
                    if (!r.ok) throw new Error('gagal');

                    const d = await r.json();
                    this.akhirAt = Date.now() + d.sisa * 1000;       // sinkron dengan jam server
                    if (JSON.stringify(this.tertunda[id]) === JSON.stringify(v)) delete this.tertunda[id];
                }
                this.status = 'ok';
            } catch (e) {
                this.status = 'tertunda';
            } finally {
                this.mengirim = false;
                this.simpanLokal();
            }
            if (this.status === 'ok' && Object.keys(this.tertunda).length) this.kirimTertunda();
        },

        /* ---------- Fase 9E: pengawasan ---------- */

        get layarPenuhDidukung() {
            const e = document.documentElement;
            return !!(e.requestFullscreen || e.webkitRequestFullscreen);
        },
        elemenLayarPenuh() { return document.fullscreenElement || document.webkitFullscreenElement || null; },

        async masukLayarPenuh() {
            const e = document.documentElement;
            try {
                await (e.requestFullscreen ? e.requestFullscreen() : e.webkitRequestFullscreen());
                this.pernahLayarPenuh = true;
            } catch (x) { /* perangkat menolak: lanjut tanpa layar penuh */ }
            this.perluLayarPenuh = false;
        },

        beri(pesan) {
            this.toast = pesan;
            clearTimeout(this.timerToast);
            this.timerToast = setTimeout(() => { this.toast = ''; }, 3000);
        },

        pasangPengawas() {
            // muat ulang atau menutup halaman tidak dihitung: pagehide membatalkan laporan yang tertunda
            window.addEventListener('pagehide', () => {
                this.menutup = true;
                clearTimeout(this.tundaKeluar);
                this.keluarTertunda = false;
            });
            window.addEventListener('pageshow', () => { this.menutup = false; });

            // pindah tab atau aplikasi: dilaporkan 0,7 detik kemudian; kalau halaman keburu ditutup, tidak jadi
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (this.selesai || this.menutup || Date.now() < this.abaikanSampai) return;
                    this.pergi = Date.now();
                    this.keluarTertunda = true;
                    this.tundaKeluar = setTimeout(() => this.kirimKeluar(), 700);
                } else if (this.pergi) {
                    if (this.keluarTertunda) this.kirimKeluar();   // peramban sempat membekukan halaman: laporkan sekarang
                    const detik = Math.round((Date.now() - this.pergi) / 1000);
                    this.pergi = null;
                    this.kejadian('kembali', detik);
                }
            });

            // jendela kehilangan fokus padahal tab masih terlihat (mis. berpindah ke jendela lain)
            window.addEventListener('blur', () => {
                if (this.selesai || this.menutup || Date.now() < this.abaikanSampai) return;
                setTimeout(() => {
                    if (!document.hidden && !document.hasFocus() && !this.menutup) this.kejadian('blur');
                }, 400);
            });

            // keluar dari layar penuh
            const ubah = () => {
                if (this.elemenLayarPenuh()) { this.perluLayarPenuh = false; this.pernahLayarPenuh = true; return; }
                if (this.pernahLayarPenuh && !this.selesai && !this.menutup) {
                    this.perluLayarPenuh = true;
                    this.kejadian('keluar_layar_penuh');
                }
            };
            document.addEventListener('fullscreenchange', ubah);
            document.addEventListener('webkitfullscreenchange', ubah);

            // salin, tempel, klik kanan: dicegah dan dicatat
            if (cfg.blokirSalin) {
                const cegah = (jenis, pesan) => (e) => {
                    e.preventDefault();
                    this.beri(pesan);
                    if (Date.now() - (this.terakhir[jenis] || 0) > 2000) {
                        this.terakhir[jenis] = Date.now();
                        this.kejadian(jenis);
                    }
                };
                document.addEventListener('copy', cegah('salin', 'Menyalin dinonaktifkan selama ujian.'));
                document.addEventListener('cut', cegah('potong', 'Memotong dinonaktifkan selama ujian.'));
                document.addEventListener('paste', cegah('tempel', 'Menempel dinonaktifkan selama ujian.'));
                document.addEventListener('contextmenu', cegah('klik_kanan', 'Klik kanan dinonaktifkan selama ujian.'));
                document.addEventListener('dragstart', (e) => e.preventDefault());
                window.addEventListener('beforeprint', () => this.kejadian('cetak'));
            }
        },

        kirimKeluar() {
            clearTimeout(this.tundaKeluar);
            this.keluarTertunda = false;
            this.kejadian('keluar_tab');
        },

        async kejadian(jenis, detik) {
            if (this.selesai) return;
            const badan = { jenis };
            if (detik !== undefined) badan.detik = detik;

            try {
                const r = await fetch(cfg.urlKejadian, {
                    method: 'POST', headers: this.header(), body: JSON.stringify(badan), keepalive: true,
                });
                if (r.status === 410) { location.href = cfg.urlHasil; return; }
                if (r.status === 401) { location.href = cfg.urlMasuk; return; }
                if (r.status === 409) { const d = await r.json(); this.henti = d.pesan; this.selesai = true; return; }
                if (!r.ok) return;
                this.terapkan(await r.json());
            } catch (e) {
                if (this.antrean.length < 20) this.antrean.push({ jenis, detik });   // jaringan putus: coba lagi nanti
            }
        },

        async kirimAntrean() {
            const daftar = this.antrean.splice(0);
            for (const a of daftar) await this.kejadian(a.jenis, a.detik);
        },

        terapkan(d) {
            this.pelanggaran = d.pelanggaran;
            this.sisaKesempatan = d.sisa;
            if (d.selesai) { this.selesai = true; location.href = cfg.urlHasil; return; }
            if (d.terkunci) { this.selesai = true; location.reload(); return; }   // layar terkunci dibuat oleh server
            if (d.dihitung) this.peringatan = true;
        },

        async kumpulkan(otomatis) {
            if (this.selesai) return;
            if (!otomatis) {
                const kosong = this.soals.length - this.jumlahTerisi;
                const tanya = kosong ? 'Masih ada ' + kosong + ' soal kosong. Kumpulkan sekarang?' : 'Kumpulkan jawaban sekarang?';
                this.abaikanSampai = Date.now() + 60000;
                const ya = confirm(tanya);
                this.abaikanSampai = Date.now() + 1500;
                if (!ya) return;
            }
            this.selesai = true;

            // pastikan semua jawaban terkirim dulu (maksimal sekitar 8 detik)
            for (let i = 0; i < 8 && Object.keys(this.tertunda).length; i++) {
                this.mengirim = false;
                await this.kirimTertunda();
                if (this.henti) return;
                if (Object.keys(this.tertunda).length) await new Promise(r => setTimeout(r, 1000));
            }
            try { localStorage.removeItem(cfg.penyimpanan); } catch (e) { /* abaikan */ }
            this.$refs.formSelesai.submit();
        },
    });
    </script>
    @endverbatim

    <div x-data="ujianSiswa({{ \Illuminate\Support\Js::from($cfg) }})">

        {{-- Bilah atas --}}
        <div class="bar-ujian">
            <div class="max-w-3xl mx-auto px-4 py-2 flex flex-wrap items-center gap-2 justify-between">
                <span class="nb-chip" :style="sisa < 60 ? 'background: var(--merah)' : 'background: var(--kuning)'">
                    <span class="judul text-xl" x-text="tampil"></span>
                </span>
                <span class="nb-chip" x-text="jumlahTerisi + '/' + soals.length + ' terisi'"></span>
                <span class="nb-chip" :style="status === 'ok' ? 'background: var(--hijau)' : 'background: var(--oranye)'"
                      x-text="status === 'ok' ? 'Tersimpan' : 'Belum terkirim: ' + jumlahTertunda"></span>
                <span class="nb-chip" style="background: var(--oranye)" x-show="pelanggaran > 0" x-cloak
                      x-text="'Keluar halaman: ' + pelanggaran + (sisaKesempatan !== null ? ' (sisa ' + sisaKesempatan + ')' : '')"></span>
                <button type="button" class="nb-btn" style="padding: .3rem .8rem" @click="kumpulkan(false)">Kumpulkan</button>
            </div>
        </div>

        <main class="max-w-3xl mx-auto p-4 pb-24" :class="blokir && 'tanpa-salin'">

            {{-- Soal --}}
            <div class="nb-card mb-4">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="nb-chip" style="background: var(--biru)" x-text="'Soal ' + (idx + 1) + ' dari ' + soals.length"></span>
                    <button type="button" class="nb-chip" :style="sekarang.r ? 'background: var(--oranye)' : ''" @click="ragu(sekarang)"
                            x-text="sekarang.r ? 'Ragu-ragu (ketuk untuk batal)' : 'Tandai ragu-ragu'"></button>
                </div>

                <p class="text-lg whitespace-pre-line" x-text="sekarang.teks"></p>

                <template x-if="sekarang.gambar">
                    <img :src="sekarang.gambar" alt="Gambar soal" class="gambar-materi mt-4">
                </template>
            </div>

            {{-- Jawaban --}}
            <div class="space-y-3 mb-6">
                <template x-if="sekarang.tipe === 'pg'">
                    <div class="space-y-3">
                        <template x-for="(o, i) in sekarang.opsi" :key="sekarang.id + o.k">
                            <button type="button" class="pilihan" :class="sekarang.j === o.k && 'dipilih'" @click="pilih(sekarang, o.k)">
                                <span class="huruf" x-text="'ABCDE'[i]"></span>
                                <span class="pt-1" x-text="o.t"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="sekarang.tipe === 'isian'">
                    <div>
                        <label class="nb-label" for="isian">Jawabanmu</label>
                        <input class="nb-input" id="isian" x-model="sekarang.j" @input="ketik(sekarang)" maxlength="500" autocomplete="off">
                    </div>
                </template>

                <template x-if="sekarang.tipe === 'esai'">
                    <div>
                        <label class="nb-label" for="esai">Jawabanmu</label>
                        <textarea class="nb-input" id="esai" rows="8" x-model="sekarang.j" @input="ketik(sekarang)" maxlength="5000"></textarea>
                    </div>
                </template>
            </div>

            {{-- Navigasi --}}
            <div class="flex justify-between gap-3 mb-6">
                <button type="button" class="nb-btn putih" @click="ke(idx - 1)" :disabled="idx === 0">&larr; Sebelumnya</button>
                <button type="button" class="nb-btn" @click="ke(idx + 1)" :disabled="idx === soals.length - 1">Berikutnya &rarr;</button>
            </div>

            <div class="nb-card">
                <p class="font-bold mb-2">Nomor soal</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(s, i) in soals" :key="s.id">
                        <button type="button" class="nomor" @click="ke(i)"
                                :class="[(s.j ?? '') !== '' ? 'terisi' : '', s.r ? 'ragu' : '', i === idx ? 'aktif' : '']"
                                x-text="i + 1"></button>
                    </template>
                </div>
                <p class="text-xs mt-3">Hijau = terisi, oranye = ragu-ragu, putih = kosong.</p>
            </div>
        </main>

        <form x-ref="formSelesai" method="POST" action="{{ route('cbt.selesai', $ujian) }}">@csrf</form>

        {{-- Peringatan setelah meninggalkan halaman --}}
        <div x-show="peringatan && !henti" x-cloak class="fixed inset-0 flex items-center justify-center p-6" style="background: rgba(255,255,255,.96); z-index: 45">
            <div class="nb-card max-w-md" style="background: var(--kuning)">
                <p class="judul text-2xl mb-2">Kamu meninggalkan halaman ujian</p>
                <p>Ini tercatat dan bisa dilihat gurumu. Pelanggaran sejauh ini: <b x-text="pelanggaran"></b>.</p>
                <p class="mt-2" x-show="sisaKesempatan !== null">
                    Sisa kesempatan: <b x-text="sisaKesempatan"></b>. Setelah habis, ujianmu
                    <span x-text="kumpulOto ? 'dikumpulkan otomatis.' : 'dihentikan sementara sampai gurumu membukanya.'"></span>
                </p>
                <button type="button" class="nb-btn mt-4" @click="peringatan = false">Mengerti, lanjut mengerjakan</button>
            </div>
        </div>

        {{-- Wajib layar penuh --}}
        <div x-show="perluLayarPenuh && !henti" x-cloak class="fixed inset-0 flex items-center justify-center p-6" style="background: #fff; z-index: 48">
            <div class="nb-card max-w-md text-center">
                <p class="judul text-2xl mb-2" x-text="pernahLayarPenuh ? 'Kamu keluar dari layar penuh' : 'Ujian ini memakai layar penuh'"></p>
                <p x-show="pernahLayarPenuh">Ini dicatat sebagai pelanggaran. Soal tampil lagi setelah kamu masuk layar penuh.</p>
                <p x-show="!pernahLayarPenuh">Tekan tombol di bawah untuk mulai. Keluar dari layar penuh akan dicatat.</p>
                <button type="button" class="nb-btn mt-4" @click="masukLayarPenuh()">Masuk layar penuh</button>
            </div>
        </div>

        {{-- Pesan singkat (mis. salin dinonaktifkan) --}}
        <div x-show="toast" x-cloak class="fixed left-0 right-0 bottom-4 flex justify-center px-4" style="z-index: 44">
            <span class="nb-chip" style="background: var(--kuning)" x-text="toast"></span>
        </div>

        {{-- Perangkat lain mengambil alih --}}
        <div x-show="henti" x-cloak class="fixed inset-0 flex items-center justify-center p-6" style="background: rgba(255,255,255,.96); z-index: 50">
            <div class="nb-card text-center max-w-md" style="background: var(--merah)">
                <p class="judul text-2xl mb-2">Ujian dihentikan di perangkat ini</p>
                <p x-text="henti"></p>
            </div>
        </div>
    </div>
</x-cbt-layout>
