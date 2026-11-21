<x-cbt-layout :judul="$ujian->judul">
    @verbatim
        <script>
            window.ujianSiswa = (cfg) => ({
                soals: cfg.soals,
                idx: 0,
                sisa: cfg.sisa,
                akhirAt: Date.now() + cfg.sisa * 1000,
                tertunda: {},
                timers: {},
                status: 'ok', // ok | tertunda
                mengirim: false,
                selesai: false,
                henti: '',
                peringatan: false,

                init() {
                    // Pulihkan jawaban yang belum terkirim (mis. halaman dimuat ulang saat offline)
                    try {
                        const s = JSON.parse(localStorage.getItem(cfg.penyimpanan) || '{}');
                        Object.entries(s).forEach(([id, v]) => {
                            const so = this.soals.find((x) => String(x.id) === String(id));
                            if (so) {
                                so.j = v.jawaban ?? '';
                                so.r = !!v.ragu;
                                this.tertunda[id] = v;
                            }
                        });
                    } catch (e) {
                        /* abaikan */
                    }

                    this.kirimTertunda();
                    setInterval(() => this.tik(), 500);
                    setInterval(() => this.kirimTertunda(), 5000);
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) {
                            this.lapor();
                        }
                    });
                },

                get sekarang() {
                    return this.soals[this.idx];
                },

                get tampil() {
                    const m = Math.floor(this.sisa / 60);
                    const d = this.sisa % 60;

                    return String(m).padStart(2, '0') + ':' + String(d).padStart(2, '0');
                },

                get jumlahTerisi() {
                    return this.soals.filter((s) => (s.j ?? '') !== '').length;
                },

                get jumlahTertunda() {
                    return Object.keys(this.tertunda).length;
                },

                tik() {
                    if (this.selesai) {
                        return;
                    }

                    this.sisa = Math.max(0, Math.round((this.akhirAt - Date.now()) / 1000));

                    if (this.sisa === 0) {
                        this.kumpulkan(true);
                    }
                },

                ke(i) {
                    this.idx = Math.max(0, Math.min(this.soals.length - 1, i));
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },

                pilih(s, huruf) {
                    s.j = huruf;
                    this.catat(s);
                },

                ketik(s) {
                    clearTimeout(this.timers[s.id]);
                    this.timers[s.id] = setTimeout(() => this.catat(s), 700);
                },

                ragu(s) {
                    s.r = !s.r;
                    this.catat(s);
                },

                catat(s) {
                    this.tertunda[s.id] = { jawaban: s.j, ragu: s.r };
                    this.simpanLokal();
                    this.kirimTertunda();
                },

                simpanLokal() {
                    try {
                        localStorage.setItem(cfg.penyimpanan, JSON.stringify(this.tertunda));
                    } catch (e) {
                        /* abaikan */
                    }
                },

                header() {
                    return {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    };
                },

                async kirimTertunda() {
                    if (this.mengirim || this.henti) {
                        return;
                    }

                    const ids = Object.keys(this.tertunda);
                    if (!ids.length) {
                        this.status = 'ok';
                        return;
                    }

                    this.mengirim = true;

                    try {
                        for (const id of ids) {
                            const v = this.tertunda[id];
                            const r = await fetch(cfg.urlJawab, {
                                method: 'POST',
                                headers: this.header(),
                                body: JSON.stringify({
                                    soal_id: Number(id),
                                    jawaban: v.jawaban,
                                    ragu: v.ragu,
                                }),
                            });

                            if (r.status === 409) {
                                const d = await r.json();
                                this.henti = d.pesan;
                                this.selesai = true;
                                return;
                            }

                            if (r.status === 410) {
                                location.href = cfg.urlHasil;
                                return;
                            }

                            if (r.status === 401) {
                                location.href = cfg.urlMasuk;
                                return;
                            }

                            if (!r.ok) {
                                throw new Error('gagal');
                            }

                            const d = await r.json();
                            this.akhirAt = Date.now() + d.sisa * 1000; // sinkron dengan jam server

                            if (JSON.stringify(this.tertunda[id]) === JSON.stringify(v)) {
                                delete this.tertunda[id];
                            }
                        }

                        this.status = 'ok';
                    } catch (e) {
                        this.status = 'tertunda';
                    } finally {
                        this.mengirim = false;
                        this.simpanLokal();
                    }

                    if (this.status === 'ok' && Object.keys(this.tertunda).length) {
                        this.kirimTertunda();
                    }
                },

                lapor() {
                    if (this.selesai) {
                        return;
                    }

                    this.peringatan = true;
                    fetch(cfg.urlPelanggaran, {
                        method: 'POST',
                        headers: this.header(),
                        keepalive: true,
                    });
                },

                async kumpulkan(otomatis) {
                    if (this.selesai) {
                        return;
                    }

                    if (!otomatis) {
                        const kosong = this.soals.length - this.jumlahTerisi;
                        const tanya = kosong
                            ? 'Masih ada ' + kosong + ' soal kosong. Kumpulkan sekarang?'
                            : 'Kumpulkan jawaban sekarang?';

                        if (!confirm(tanya)) {
                            return;
                        }
                    }

                    this.selesai = true;

                    // Pastikan semua jawaban terkirim dulu (maksimal sekitar 8 detik)
                    for (let i = 0; i < 8 && Object.keys(this.tertunda).length; i++) {
                        this.mengirim = false;
                        await this.kirimTertunda();
                        if (this.henti) {
                            return;
                        }
                        if (Object.keys(this.tertunda).length) {
                            await new Promise((r) => setTimeout(r, 1000));
                        }
                    }

                    try {
                        localStorage.removeItem(cfg.penyimpanan);
                    } catch (e) {
                        /* abaikan */
                    }

                    this.$refs.formSelesai.submit();
                },
            });
        </script>
    @endverbatim

    <div x-data="ujianSiswa({{ \Illuminate\Support\Js::from($cfg) }})">
        {{-- Bilah atas --}}
        <div class="bar-ujian">
            <div class="max-w-3xl mx-auto px-4 py-2 flex flex-wrap items-center gap-2 justify-between">
                <span
                    class="nb-chip"
                    :style="sisa < 60 ? 'background: var(--merah)' : 'background: var(--kuning)'"
                >
                    <span class="judul text-xl" x-text="tampil"></span>
                </span>

                <span class="nb-chip" x-text="jumlahTerisi + '/' + soals.length + ' terisi'"></span>

                <span
                    class="nb-chip"
                    :style="status === 'ok' ? 'background: var(--hijau)' : 'background: var(--oranye)'"
                    x-text="status === 'ok' ? 'Tersimpan' : 'Belum terkirim: ' + jumlahTertunda"
                ></span>

                <button
                    type="button"
                    class="nb-btn"
                    style="padding: .3rem .8rem"
                    @click="kumpulkan(false)"
                >
                    Kumpulkan
                </button>
            </div>
        </div>

        <main class="max-w-3xl mx-auto p-4 pb-24">
            <div
                class="nb-card mb-4"
                style="background: var(--kuning)"
                x-show="peringatan"
                x-cloak
            >
                <p class="font-bold">Kamu sempat meninggalkan halaman ujian.</p>
                <p class="text-sm">
                    Ini dicatat dan bisa dilihat gurumu. Tetap di halaman ini sampai selesai.
                </p>
                <button
                    type="button"
                    class="underline font-bold mt-1"
                    @click="peringatan = false"
                >
                    Mengerti
                </button>
            </div>

            {{-- Soal --}}
            <div class="nb-card mb-4">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span
                        class="nb-chip"
                        style="background: var(--biru)"
                        x-text="'Soal ' + (idx + 1) + ' dari ' + soals.length"
                    ></span>

                    <button
                        type="button"
                        class="nb-chip"
                        :style="sekarang.r ? 'background: var(--oranye)' : ''"
                        @click="ragu(sekarang)"
                        x-text="sekarang.r ? 'Ragu-ragu (ketuk untuk batal)' : 'Tandai ragu-ragu'"
                    ></button>
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
                            <button
                                type="button"
                                class="pilihan"
                                :class="sekarang.j === o.k && 'dipilih'"
                                @click="pilih(sekarang, o.k)"
                            >
                                <span class="huruf" x-text="'ABCDE'[i]"></span>
                                <span class="pt-1" x-text="o.t"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="sekarang.tipe === 'isian'">
                    <div>
                        <label class="nb-label" for="isian">Jawabanmu</label>
                        <input
                            class="nb-input"
                            id="isian"
                            x-model="sekarang.j"
                            @input="ketik(sekarang)"
                            maxlength="500"
                            autocomplete="off"
                        >
                    </div>
                </template>

                <template x-if="sekarang.tipe === 'esai'">
                    <div>
                        <label class="nb-label" for="esai">Jawabanmu</label>
                        <textarea
                            class="nb-input"
                            id="esai"
                            rows="8"
                            x-model="sekarang.j"
                            @input="ketik(sekarang)"
                            maxlength="5000"
                        ></textarea>
                    </div>
                </template>
            </div>

            {{-- Navigasi --}}
            <div class="flex justify-between gap-3 mb-6">
                <button
                    type="button"
                    class="nb-btn putih"
                    @click="ke(idx - 1)"
                    :disabled="idx === 0"
                >
                    &larr; Sebelumnya
                </button>

                <button
                    type="button"
                    class="nb-btn"
                    @click="ke(idx + 1)"
                    :disabled="idx === soals.length - 1"
                >
                    Berikutnya &rarr;
                </button>
            </div>

            <div class="nb-card">
                <p class="font-bold mb-2">Nomor soal</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(s, i) in soals" :key="s.id">
                        <button
                            type="button"
                            class="nomor"
                            @click="ke(i)"
                            :class="[(s.j ?? '') !== '' ? 'terisi' : '', s.r ? 'ragu' : '', i === idx ? 'aktif' : '']"
                            x-text="i + 1"
                        ></button>
                    </template>
                </div>
                <p class="text-xs mt-3">
                    Hijau = terisi, oranye = ragu-ragu, putih = kosong.
                </p>
            </div>
        </main>

        <form x-ref="formSelesai" method="POST" action="{{ route('cbt.selesai', $ujian) }}">
            @csrf
        </form>

        {{-- Perangkat lain mengambil alih --}}
        <div
            x-show="henti"
            x-cloak
            class="fixed inset-0 flex items-center justify-center p-6"
            style="background: rgba(255,255,255,.96); z-index: 50"
        >
            <div class="nb-card text-center max-w-md" style="background: var(--merah)">
                <p class="judul text-2xl mb-2">Ujian dihentikan di perangkat ini</p>
                <p x-text="henti"></p>
            </div>
        </div>
    </div>
</x-cbt-layout>