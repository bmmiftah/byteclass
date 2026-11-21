<x-guru-layout :judul="'Alat Kelas ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <h1 class="text-3xl mt-3 mb-6">Alat Kelas {{ $kelas->nama }}</h1>

    @verbatim
        <script>
            window.alat = (daftar) => ({
                daftar,
                keluar: daftar.filter(s => !s.hadir).map(s => s.id),
                sudah: [],
                terpilih: null,
                jalan: false,
                tanpaUlang: true,

                get pool() {
                    return this.daftar.filter(s => (
                        !this.keluar.includes(s.id)
                        && !(this.tanpaUlang && this.sudah.includes(s.id))
                    ));
                },

                toggle(id) {
                    this.keluar = this.keluar.includes(id)
                        ? this.keluar.filter(x => x !== id)
                        : [...this.keluar, id];
                },

                acak() {
                    if (this.jalan || !this.pool.length) return;

                    this.jalan = true;
                    const p = this.pool;
                    let n = 0;

                    const t = setInterval(() => {
                        this.terpilih = p[Math.floor(Math.random() * p.length)];

                        if (++n >= 22) {
                            clearInterval(t);
                            const akhir = p[Math.floor(Math.random() * p.length)];
                            this.terpilih = akhir;
                            this.sudah.push(akhir.id);
                            this.jalan = false;
                        }
                    }, 70);
                },

                reset() {
                    this.sudah = [];
                    this.terpilih = null;
                },

                layar(el) {
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    } else if (el.requestFullscreen) {
                        el.requestFullscreen();
                    }
                },

                /* ---- timer ---- */
                menit: 5,
                sisa: 300,
                akhirAt: null,
                tik: null,
                selesai: false,

                get tampil() {
                    const m = Math.floor(this.sisa / 60);
                    const d = this.sisa % 60;

                    return String(m).padStart(2, '0') + ':' + String(d).padStart(2, '0');
                },

                atur(m) {
                    this.berhenti();
                    this.menit = m;
                    this.sisa = m * 60;
                    this.selesai = false;
                },

                mulai() {
                    if (this.tik || this.sisa <= 0) return;

                    this.selesai = false;
                    this.akhirAt = Date.now() + this.sisa * 1000;

                    this.tik = setInterval(() => {
                        this.sisa = Math.max(0, Math.round((this.akhirAt - Date.now()) / 1000));

                        if (this.sisa === 0) {
                            this.berhenti();
                            this.selesai = true;
                            this.bunyi();
                        }
                    }, 250);
                },

                berhenti() {
                    clearInterval(this.tik);
                    this.tik = null;
                },

                ulang() {
                    this.atur(this.menit);
                },

                bunyi() {
                    try {
                        const c = new (window.AudioContext || window.webkitAudioContext)();

                        [0, 0.35, 0.7].forEach(t => {
                            const o = c.createOscillator();
                            const g = c.createGain();
                            o.frequency.value = 880;
                            o.connect(g);
                            g.connect(c.destination);
                            g.gain.setValueAtTime(0.3, c.currentTime + t);
                            o.start(c.currentTime + t);
                            o.stop(c.currentTime + t + 0.25);
                        });
                    } catch (e) {
                        /* tanpa suara */
                    }
                },
            });
        </script>
    @endverbatim

    <div x-data="alat({{ \Illuminate\Support\Js::from($daftar) }})" class="grid gap-8 lg:grid-cols-2">
        {{-- Pemilih acak --}}
        <section class="nb-card panggung space-y-4" x-ref="panggungPilih" style="background: var(--kuning)">
            <h2 class="text-xl">Pemilih Acak</h2>

            <div
                class="nb-card bg-white flex items-center justify-center"
                style="min-height: 9rem"
                :class="!jalan && terpilih && 'pop'"
            >
                <p class="layar-nama" x-text="terpilih ? terpilih.nama : '?'"></p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="nb-btn" @click="acak()" :disabled="jalan || !pool.length">Pilih!</button>
                <button type="button" class="nb-btn putih" @click="reset()">Ulang dari awal</button>
                <button type="button" class="nb-btn putih" @click="layar($refs.panggungPilih)">Layar penuh</button>
            </div>

            <label class="flex items-center gap-2 font-bold">
                <input type="checkbox" x-model="tanpaUlang" class="w-5 h-5"> Tanpa pengulangan
            </label>

            <p class="text-sm">
                Tersisa di undian: <b x-text="pool.length"></b> dari <b x-text="daftar.length"></b> siswa.
                Ketuk nama di bawah untuk mengeluarkan / memasukkan.
            </p>

            <div class="flex flex-wrap gap-2">
                <template x-for="s in daftar" :key="s.id">
                    <button
                        type="button"
                        @click="toggle(s.id)"
                        class="nb-chip"
                        :style="keluar.includes(s.id) ? 'background:#fff;text-decoration:line-through;opacity:.6' : (sudah.includes(s.id) ? 'background:var(--biru)' : 'background:var(--hijau)')"
                        x-text="s.nama"
                    ></button>
                </template>
            </div>

            <p class="text-xs">Siswa yang hari ini berstatus S/I/A di presensi otomatis dikeluarkan.</p>
        </section>

        {{-- Timer --}}
        <section class="nb-card panggung space-y-4" x-ref="panggungWaktu" style="background: var(--biru)">
            <h2 class="text-xl">Timer</h2>

            <div class="nb-card bg-white" :class="selesai && 'kedip'">
                <p class="layar-waktu" x-text="tampil"></p>
            </div>

            <div class="flex flex-wrap gap-2">
                <template x-for="m in [1, 3, 5, 10, 15, 30]" :key="m">
                    <button
                        type="button"
                        class="nb-btn putih"
                        style="padding: .35rem .8rem"
                        @click="atur(m)"
                        x-text="m + ' mnt'"
                    ></button>
                </template>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="nb-btn hijau" @click="mulai()" x-show="!tik">Mulai</button>
                <button type="button" class="nb-btn kuning" @click="berhenti()" x-show="tik">Jeda</button>
                <button type="button" class="nb-btn putih" @click="ulang()">Atur ulang</button>
                <button type="button" class="nb-btn putih" @click="layar($refs.panggungWaktu)">Layar penuh</button>
            </div>

            <p class="text-xs">Berbunyi tiga kali saat waktu habis. Layar harus tetap menyala agar suara berbunyi.</p>
        </section>
    </div>
</x-guru-layout>