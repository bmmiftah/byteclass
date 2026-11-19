<x-guru-layout :judul="'Edit ' . $sesi->nama">
    <div x-data="editorKelompok(@js($data))" class="space-y-5">
        <a href="{{ route('kelompok.index', $kelas) }}" class="font-bold underline">&larr; Kelompok {{ $kelas->nama }}</a>

        {{-- Nama sesi + tombol utama --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex-1 min-w-[220px]">
                <label class="nb-label" for="nama">Nama sesi</label>
                <input id="nama" class="nb-input" x-model="sesi.nama" @input="kotor = true">
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" class="nb-btn putih" @click="tambahKelompok()">+ Kelompok</button>
                <button type="button" class="nb-btn biru" @click="simpan()" :disabled="sibuk">Simpan</button>
                <button type="button" class="nb-btn" style="font-size: 1.1rem" @click="acak()" :disabled="sibuk">BAGI!</button>
            </div>
        </div>

        {{-- Opsi --}}
        <div class="nb-card grid gap-4 md:grid-cols-4 items-end">
            <div>
                <label class="nb-label" for="metode">Cara membagi</label>
                <select id="metode" class="nb-input" x-model="metode">
                    <option value="acak">Acak</option>
                    <option value="campur_gender">Campur L/P</option>
                </select>
            </div>

            <label class="flex items-center gap-2 font-bold">
                <input type="checkbox" x-model="hindariUlang">
                Hindari pasangan yang sama seperti sesi lalu
            </label>

            <label class="flex items-center gap-2 font-bold">
                <input type="checkbox" x-model="acakKetua">
                Pilih ketua acak
            </label>

            <label class="flex items-center gap-2 font-bold">
                <input type="checkbox" x-model="sesi.nama_lengkap" @change="kotor = true">
                Nama lengkap di link publik
            </label>

            <div class="md:col-span-2 flex gap-2 items-end">
                <div class="flex-1">
                    <label class="nb-label" for="tema">Nama kelompok</label>
                    <select id="tema" class="nb-input" x-model="temaPilih">
                        <option value="angka">Kelompok 1, 2, 3...</option>
                        <option value="warna">Warna</option>
                        <option value="hewan">Hewan</option>
                        <option value="planet">Planet</option>
                        <option value="buah">Buah</option>
                    </select>
                </div>

                <button type="button" class="nb-btn putih" @click="namaOtomatis()">Terapkan</button>
            </div>
        </div>

        {{-- Status --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="nb-chip">Kelompok: <span x-text="kelompoks.length"></span></span>
            <span class="nb-chip">Siswa: <span x-text="siswas.length"></span></span>
            <span class="nb-chip" style="background: var(--oranye)" x-show="belum.length">
                Belum dapat kelompok: <span x-text="belum.length"></span>
            </span>
            <span class="nb-chip" style="background: var(--oranye)" x-show="kotor">Belum disimpan</span>
        </div>

        {{-- Bilah siswa terpilih --}}
        <div
            class="nb-card flex flex-wrap items-center gap-3 sticky top-2 z-30"
            style="background: var(--kuning); display: none"
            x-show="pilih !== null"
        >
            <span class="font-bold">Dipilih: <span x-text="namaSiswa(pilih)"></span></span>

            <template x-if="dataPilih && dataPilih.p">
                <span class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="nb-btn putih"
                        @click="toggleKunci()"
                        x-text="dataPilih.anggota.terkunci ? 'Lepas kunci' : 'Kunci di kelompok ini'"
                    ></button>
                    <button type="button" class="nb-btn putih" @click="jadikanKetua()" :disabled="dataPilih.ketua">Jadikan ketua</button>
                    <button type="button" class="nb-btn putih" @click="keluarkanPilih()">Keluarkan</button>
                </span>
            </template>

            <span class="text-sm">Ketuk tombol "Pindah ke sini" pada kelompok tujuan.</span>
            <button type="button" class="nb-btn putih" @click="pilih = null">Batal</button>
        </div>

        <div class="grid gap-6 lg:grid-cols-4">
            {{-- Kelompok --}}
            <div class="lg:col-span-3 grid gap-5 sm:grid-cols-2">
                <template x-for="(k, i) in kelompoks" :key="k.id ?? ('b' + i)">
                    <div
                        class="nb-card kartu-kelompok"
                        :style="'background:' + warna(i)"
                        @dragover.prevent
                        @drop.prevent="jatuhKe(i)"
                    >
                        <div class="flex items-center gap-2 mb-3">
                            <input class="nb-input" x-model="k.nama" @input="kotor = true" aria-label="Nama kelompok">
                            <button
                                type="button"
                                class="nb-btn putih"
                                style="padding: .25rem .6rem"
                                @click="hapusKelompok(i)"
                                aria-label="Hapus kelompok"
                            >X</button>
                        </div>

                        <p class="font-bold mb-2"><span x-text="k.anggota.length"></span> anggota</p>

                        <div class="zona">
                            <template x-for="a in k.anggota" :key="a.siswa_id">
                                <button
                                    type="button"
                                    class="chip-siswa baru"
                                    :class="{ pilih: pilih === a.siswa_id, ketua: k.ketua === a.siswa_id }"
                                    :style="tersembunyi(a.siswa_id) ? 'visibility:hidden' : ''"
                                    draggable="true"
                                    @dragstart="seret = a.siswa_id"
                                    @click="pilihSiswa(a.siswa_id)"
                                >
                                    <span x-text="namaSiswa(a.siswa_id)"></span>
                                    <span class="tag" x-show="k.ketua === a.siswa_id">ketua</span>
                                    <span class="tag" x-show="a.terkunci">kunci</span>
                                </button>
                            </template>
                        </div>

                        <button
                            type="button"
                            class="nb-btn putih w-full mt-3"
                            x-show="pilih !== null"
                            style="display: none"
                            @click="pindahKe(i)"
                        >Pindah ke sini</button>
                    </div>
                </template>
            </div>

            {{-- Samping --}}
            <div class="space-y-5">
                <div class="nb-card" @dragover.prevent @drop.prevent="jatuhBelum()">
                    <h3 class="text-lg mb-3">Belum dapat kelompok</h3>
                    <p class="text-sm" x-show="belum.length === 0">Semua siswa sudah punya kelompok.</p>

                    <div class="zona">
                        <template x-for="s in belum" :key="s.id">
                            <button
                                type="button"
                                class="chip-siswa"
                                :class="{ pilih: pilih === s.id }"
                                draggable="true"
                                @dragstart="seret = s.id"
                                @click="pilihSiswa(s.id)"
                            >
                                <span x-text="s.tampil"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="nb-card">
                    <h3 class="text-lg mb-3">Tampilkan &amp; bagikan</h3>
                    <a class="nb-btn biru w-full" :href="urls.publik" target="_blank">Buka layar tampilan</a>
                    <button type="button" class="nb-btn putih w-full mt-3" @click="salin()">Salin link</button>
                    <canvas x-ref="qr" class="mt-3 mx-auto border-2 border-black"></canvas>
                    <p class="text-sm mt-2">Link hanya menampilkan pembagian kelompok, tanpa login.</p>
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