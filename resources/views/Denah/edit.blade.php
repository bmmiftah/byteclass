<x-guru-layout :judul="'Edit ' . $denah->nama">
    <div x-data="editorDenah(@js($data))" class="space-y-5">
        <a href="{{ route('denah.index', $kelas) }}" class="font-bold underline">&larr; Denah {{ $kelas->nama }}</a>

        {{-- Baris atas: nama + tombol utama --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex-1 min-w-[220px]">
                <label class="nb-label" for="nama">Nama denah</label>
                <input id="nama" class="nb-input" x-model="denah.nama" @input="kotor = true">
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    class="nb-btn putih"
                    @click="mode = (mode === 'tambah' ? 'pilih' : 'tambah')"
                    :style="mode === 'tambah' ? 'background: var(--oranye)' : ''"
                    x-text="mode === 'tambah' ? 'Selesai tambah' : '+ Tambah meja'"
                ></button>
                <button type="button" class="nb-btn biru" @click="simpan()" :disabled="sibuk">Simpan layout</button>
                <button type="button" class="nb-btn" style="font-size: 1.1rem" @click="acak()" :disabled="sibuk">ACAK!</button>
            </div>
        </div>

        {{-- Status --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="nb-chip">Meja: <span x-text="mejas.length"></span></span>
            <span class="nb-chip">Siswa: <span x-text="siswas.length"></span></span>
            <span class="nb-chip" style="background: var(--merah)" x-show="kurang">Meja kurang dari siswa!</span>
            <span class="nb-chip" style="background: var(--oranye)" x-show="kotor">Belum disimpan</span>
            <span class="nb-chip" style="background: var(--kuning)" x-show="mode === 'tambah'">Ketuk kotak kosong untuk menaruh meja</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Kanvas --}}
            <div class="lg:col-span-2 space-y-3">
                <div class="papan">PAPAN TULIS / DEPAN KELAS</div>

                <div class="nb-scroll">
                    <div
                        x-ref="kanvas"
                        class="kanvas"
                        :style="gayaKanvas"
                        @click.self="klikKanvas($event)"
                    >
                        <template x-for="(m, i) in mejas" :key="m.id ?? ('b' + i)">
                            <div
                                class="meja"
                                :class="{ pilih: pilih === i, kunci: m.terkunci, putar: putar.has(m.id), kosong: !namaMeja(m) }"
                                :style="gayaMeja(m)"
                                @pointerdown="mulaiGeser($event, i)"
                                @pointermove="geserBergerak($event)"
                                @pointerup="selesaiGeser()"
                                @pointercancel="selesaiGeser()"
                            >
                                <div>
                                    <span x-text="namaMeja(m) || '(kosong)'"></span>
                                    <span class="tag" x-show="m.terkunci">kunci</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <p class="text-sm">Geser meja untuk memindahkan. Ketuk meja untuk mengaturnya di panel.</p>
            </div>

            {{-- Panel --}}
            <div class="space-y-5">
                {{-- Meja terpilih --}}
                <div class="nb-card" x-show="mejaPilih" style="display: none">
                    <h3 class="text-lg mb-3">Meja terpilih</h3>

                    <label class="nb-label" for="pilihSiswa">Siswa di meja ini</label>
                    <select id="pilihSiswa" class="nb-input" @change="setSiswa($event.target.value)">
                        <option value="" :selected="!mejaPilih?.siswa_id">(kosong)</option>
                        <template x-for="s in siswas" :key="s.id">
                            <option :value="s.id" :selected="mejaPilih?.siswa_id === s.id" x-text="s.nama"></option>
                        </template>
                    </select>

                    <label class="flex items-center gap-2 mt-3 font-bold">
                        <input type="checkbox" :checked="mejaPilih?.terkunci" @change="toggleKunci()">
                        Kunci (isi tidak ikut diacak)
                    </label>

                    <button type="button" class="nb-btn merah mt-4 w-full" @click="hapusPilih()">Hapus meja ini</button>
                </div>

                {{-- Pengaturan --}}
                <div class="nb-card">
                    <h3 class="text-lg mb-3">Pengaturan</h3>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="nb-label" for="kolom">Kolom</label>
                            <input id="kolom" type="number" min="3" max="16" class="nb-input" x-model.number="denah.lebar" @change="ubahUkuran()">
                        </div>

                        <div>
                            <label class="nb-label" for="baris">Baris</label>
                            <input id="baris" type="number" min="3" max="12" class="nb-input" x-model.number="denah.tinggi" @change="ubahUkuran()">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 mt-3 font-bold">
                        <input type="checkbox" x-model="hindariSama">
                        Hindari posisi sama seperti acakan terakhir
                    </label>

                    <label class="flex items-center gap-2 mt-2 font-bold">
                        <input type="checkbox" x-model="denah.nama_lengkap" @change="kotor = true">
                        Tampilkan nama lengkap di link publik
                    </label>
                </div>

                {{-- Aturan pisah --}}
                <div class="nb-card">
                    <h3 class="text-lg mb-3">Jangan berdekatan</h3>
                    <p class="text-sm mb-2">Dua siswa di bawah ini tidak akan duduk bersebelahan (termasuk diagonal).</p>

                    <div class="space-y-2">
                        <select class="nb-input" x-model="aturanA">
                            <option value="">Siswa pertama</option>
                            <template x-for="s in siswas" :key="'a' + s.id">
                                <option :value="s.id" x-text="s.nama"></option>
                            </template>
                        </select>

                        <select class="nb-input" x-model="aturanB">
                            <option value="">Siswa kedua</option>
                            <template x-for="s in siswas" :key="'b' + s.id">
                                <option :value="s.id" x-text="s.nama"></option>
                            </template>
                        </select>

                        <button type="button" class="nb-btn putih w-full" @click="tambahAturan()">+ Tambah aturan</button>
                    </div>

                    <ul class="mt-3 space-y-2">
                        <template x-for="a in aturan" :key="a.id">
                            <li class="flex items-center justify-between gap-2 border-2 border-black p-2">
                                <span class="text-sm">
                                    <span x-text="namaSiswa(a.siswa_a)"></span> &times; <span x-text="namaSiswa(a.siswa_b)"></span>
                                </span>
                                <button type="button" class="font-bold underline" @click="hapusAturan(a.id)">Hapus</button>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Bagikan --}}
                <div class="nb-card">
                    <h3 class="text-lg mb-3">Tampilkan &amp; bagikan</h3>
                    <a class="nb-btn biru w-full" :href="urls.publik" target="_blank">Buka layar tampilan</a>
                    <button type="button" class="nb-btn putih w-full mt-3" @click="salin()">Salin link</button>
                    <canvas x-ref="qr" class="mt-3 mx-auto border-2 border-black"></canvas>
                    <p class="text-sm mt-2">Siswa memindai QR ini untuk melihat posisi duduk di HP. Link hanya menampilkan denah, tanpa login.</p>
                </div>

                {{-- Riwayat --}}
                <div class="nb-card">
                    <h3 class="text-lg mb-3">Riwayat acak</h3>
                    <p class="text-sm" x-show="riwayat.length === 0">Belum ada. Tekan ACAK! untuk memulai.</p>

                    <ul class="space-y-2">
                        <template x-for="r in riwayat" :key="r.id">
                            <li class="flex items-center justify-between gap-2 border-2 border-black p-2">
                                <span class="text-sm" x-text="r.waktu"></span>
                                <button type="button" class="font-bold underline" @click="pulihkan(r.id)">Pulihkan</button>
                            </li>
                        </template>
                    </ul>
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