<x-guru-layout :judul="'Nilai ' . $penilaian->judul">
    <div x-data="nilaiCepat(@js($data))" class="space-y-4">
        {{-- Kepala --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('penilaian.show', $penilaian) }}" class="font-bold underline">&larr; Rekap</a>
                <h1 class="text-2xl md:text-3xl mt-1">{{ $penilaian->judul }}</h1>
                <p class="text-sm">
                    KKM {{ $penilaian->kkm }} &middot; <span x-text="selesai + ' dari ' + units.length + ' selesai'"></span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="nb-chip"
                    :style="status === 'tersimpan' ? 'background: var(--hijau)' : (status === 'menyimpan' ? 'background: var(--kuning)' : 'background: var(--oranye)')"
                    x-text="status === 'tersimpan' ? 'Tersimpan' : (status === 'menyimpan' ? 'Menyimpan...' : 'Menunggu sinyal (' + Object.keys(antrian).length + ' belum terkirim)')"
                ></span>

                @if ($penilaian->jenis === 'kelompok')
                    <div class="flex">
                        <button
                            type="button"
                            class="nb-btn putih"
                            :style="mode === 'kelompok' ? 'background: var(--kuning)' : ''"
                            @click="gantiMode('kelompok')"
                        >Per kelompok</button>
                        <button
                            type="button"
                            class="nb-btn putih"
                            :style="mode === 'siswa' ? 'background: var(--kuning)' : ''"
                            @click="gantiMode('siswa')"
                        >Per siswa</button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Navigasi --}}
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="nb-btn putih"
                @click="pindah(-1)"
                :disabled="i === 0"
                aria-label="Sebelumnya"
            >&larr;</button>

            <button type="button" class="nb-btn flex-1" @click="daftarBuka = true">
                <span x-text="(i + 1) + ' / ' + units.length"></span>&nbsp;&middot; Daftar
            </button>

            <button
                type="button"
                class="nb-btn putih"
                @click="pindah(1)"
                :disabled="i >= units.length - 1"
                aria-label="Berikutnya"
            >&rarr;</button>
        </div>

        {{-- Kartu penilaian --}}
        <template x-if="unit">
            <div class="nb-card space-y-4" @touchstart="sentuhMulai($event)" @touchend="sentuhSelesai($event)">
                <div>
                    <h2 class="text-2xl" x-text="unit.nama"></h2>
                    <p class="text-sm" x-show="unit.sub" x-text="unit.sub"></p>
                    <p class="text-sm font-bold" x-show="unit.ids.length === 0">Kelompok ini belum punya anggota.</p>
                </div>

                <template x-for="k in kriteria" :key="k.id">
                    <div class="border-2 border-black p-3">
                        <div class="flex justify-between gap-2 mb-2">
                            <span class="font-bold" x-text="k.nama"></span>
                            <span class="text-sm" x-text="'bobot ' + k.bobot"></span>
                        </div>

                        <template x-if="k.tipe === 'poin'">
                            <div class="flex flex-wrap gap-2">
                                <template x-for="n in rentang(k.maks)" :key="n">
                                    <button
                                        type="button"
                                        class="nb-btn putih poin"
                                        :class="{ aktif: cur.detail[k.id] === n }"
                                        @click="set(k.id, n)"
                                        x-text="n"
                                    ></button>
                                </template>
                            </div>
                        </template>

                        <template x-if="k.tipe === 'angka'">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="nb-btn putih poin"
                                    @click="geser(k.id, -1)"
                                    aria-label="Kurangi"
                                >-</button>

                                <input
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    :max="k.maks"
                                    class="nb-input"
                                    style="text-align: center"
                                    x-model.number="cur.detail[k.id]"
                                    @input="ubah()"
                                >

                                <button
                                    type="button"
                                    class="nb-btn putih poin"
                                    @click="geser(k.id, 1)"
                                    aria-label="Tambah"
                                >+</button>

                                <span class="text-sm whitespace-nowrap" x-text="'/ ' + k.maks"></span>
                            </div>
                        </template>

                        <template x-if="k.tipe === 'ceklis'">
                            <button
                                type="button"
                                class="nb-btn putih w-full"
                                :class="{ aktif: cur.detail[k.id] > 0 }"
                                @click="set(k.id, cur.detail[k.id] > 0 ? 0 : 1)"
                                x-text="cur.detail[k.id] > 0 ? 'Ya' : 'Tidak'"
                            ></button>
                        </template>
                    </div>
                </template>

                <div>
                    <label class="nb-label" for="catatan">Catatan (opsional)</label>
                    <textarea id="catatan" class="nb-input" rows="2" x-model="cur.catatan" @input="ubah()"></textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm">Nilai akhir</p>
                        <p
                            class="judul text-4xl"
                            :style="tuntas ? 'color: #0a7a2f' : 'color: #b34700'"
                            x-text="totalLive.toFixed(1)"
                        ></p>
                        <p class="text-sm font-bold" x-text="tuntas ? 'Tuntas' : 'Belum tuntas'"></p>
                    </div>

                    <button
                        type="button"
                        class="nb-btn"
                        style="font-size: 1.1rem"
                        @click="simpanLanjut()"
                        :disabled="!unit.ids.length"
                    >Simpan &amp; lanjut</button>
                </div>

                <p class="text-xs">Tersimpan otomatis. Geser kartu ke kiri/kanan untuk berpindah.</p>
            </div>
        </template>

        {{-- Daftar semua unit --}}
        <div x-show="daftarBuka" class="fixed inset-0 z-40 bg-white overflow-auto p-4" style="display: none">
            <div class="max-w-5xl mx-auto">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl">Daftar</h2>
                    <button type="button" class="nb-btn" @click="daftarBuka = false">Tutup</button>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                    <template x-for="(u, n) in units" :key="u.kunci">
                        <button
                            type="button"
                            class="nb-card text-left"
                            :style="'background:' + warnaStatus(statusUnit(u))"
                            @click="lompat(n)"
                        >
                            <span class="font-bold block" x-text="(n + 1) + '. ' + u.nama"></span>
                            <span class="text-sm block" x-text="infoUnit(u)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Toast --}}
        <div
            x-show="toast"
            x-transition
            class="fixed top-4 right-4 left-4 sm:left-auto sm:max-w-sm nb-card z-50"
            :style="toastError ? 'background: var(--merah)' : 'background: var(--hijau)'"
            x-text="toast"
            style="display: none"
        ></div>
    </div>
</x-guru-layout>