<x-guru-layout :judul="'Rubrik ' . $rubrik->nama">
    <div x-data="editorRubrik(@js($data))" class="space-y-5">
        <a href="{{ route('rubrik.index') }}" class="font-bold underline">&larr; Semua rubrik</a>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex-1 min-w-[220px]">
                <label class="nb-label" for="nama">Nama rubrik</label>
                <input id="nama" class="nb-input" x-model="nama" @input="kotor = true">
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" class="nb-btn putih" @click="tambah()">+ Kriteria</button>
                <button type="button" class="nb-btn biru" @click="simpan()" :disabled="sibuk">Simpan rubrik</button>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="nb-chip">Kriteria: <span x-text="kriterias.length"></span></span>
            <span class="nb-chip">Total bobot: <span x-text="totalBobot"></span></span>
            <span class="nb-chip" style="background: var(--oranye)" x-show="kotor">Belum disimpan</span>
        </div>

        <div class="space-y-4">
            <template x-for="(k, i) in kriterias" :key="i">
                <div class="nb-card grid gap-4 md:grid-cols-12 items-end">
                    <div class="md:col-span-4">
                        <label class="nb-label">Nama kriteria</label>
                        <input
                            class="nb-input"
                            x-model="k.nama"
                            @input="kotor = true"
                            placeholder="mis. Ketepatan langkah kerja"
                        >
                    </div>

                    <div class="md:col-span-3">
                        <label class="nb-label">Jenis</label>
                        <select class="nb-input" x-model="k.tipe" @change="ubahTipe(k)">
                            <option value="angka">Angka (0 - maks)</option>
                            <option value="poin">Poin (1 - maks)</option>
                            <option value="ceklis">Ceklis (Ya/Tidak)</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="nb-label" x-text="k.tipe === 'poin' ? 'Poin maks (2-10)' : 'Nilai maks'"></label>
                        <input
                            class="nb-input"
                            type="number"
                            min="1"
                            max="1000"
                            x-model.number="k.maks"
                            @input="kotor = true"
                            :disabled="k.tipe === 'ceklis'"
                        >
                    </div>

                    <div class="md:col-span-1">
                        <label class="nb-label">Bobot</label>
                        <input
                            class="nb-input"
                            type="number"
                            min="1"
                            max="100"
                            x-model.number="k.bobot"
                            @input="kotor = true"
                        >
                    </div>

                    <div class="md:col-span-2 flex items-center gap-2">
                        <span class="nb-chip" x-text="persen(k) + '%'"></span>
                        <button
                            type="button"
                            class="nb-btn putih"
                            style="padding: .25rem .6rem"
                            @click="naik(i)"
                            aria-label="Naik"
                        >&uarr;</button>
                        <button
                            type="button"
                            class="nb-btn putih"
                            style="padding: .25rem .6rem"
                            @click="turun(i)"
                            aria-label="Turun"
                        >&darr;</button>
                        <button
                            type="button"
                            class="nb-btn merah"
                            style="padding: .25rem .6rem"
                            @click="hapus(i)"
                            aria-label="Hapus"
                        >X</button>
                    </div>
                </div>
            </template>
        </div>

        <div class="nb-card" style="background: var(--kuning)">
            <p class="font-bold">Cara membaca</p>
            <p class="text-sm mt-1">
                Setiap kriteria diubah ke skala 0-100 lalu dirata-rata berbobot. Bobot 2 berarti kriteria itu dua kali lebih
                berpengaruh dari bobot 1. Persentase di tiap baris adalah porsi pengaruhnya.
            </p>
        </div>

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