<x-guru-layout :judul="'Pantau '.$ujian->judul">
    <a href="{{ route('ujian.index', $ujian->kelas_id) }}" class="font-bold underline">&larr; Ujian {{ $ujian->kelas->nama }}</a>
    <h1 class="text-3xl mt-3 mb-2">Pantau: {{ $ujian->judul }}</h1>

    @verbatim
    <script>
    window.pantau = (urlData, urlAksi, urlKunci, urlLog) => ({
        data: { total: 0, status: '', baris: [] },
        galat: '',
        tik: null,
        init() {
            this.muat();
            this.tik = setInterval(() => { if (!document.hidden) this.muat(); }, 10000);
        },
        async muat() {
            try {
                const r = await fetch(urlData, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('gagal');
                this.data = await r.json();
                this.galat = '';
            } catch (e) { this.galat = 'Gagal memuat. Mencoba lagi...'; }
        },
        menit(d) { return d === null ? '-' : Math.floor(d / 60) + ':' + String(d % 60).padStart(2, '0'); },
        async aksi(pid, aksi, tanya) {
            if (tanya && !confirm(tanya)) return;
            await fetch(urlAksi.replace('PID', pid), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ aksi }),
            });
            this.muat();
        },
        async bukaKunci(pid) {
            await fetch(urlKunci.replace('PID', pid), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            });
            this.muat();
        },
        log(pid) { return urlLog.replace('PID', pid); },
        hitung(s) { return this.data.baris.filter(b => b.status === s).length; },
        terkunci() { return this.data.baris.filter(b => b.terkunci).length; },
    });
    </script>
    @endverbatim

    <div x-data="pantau('{{ route('ujian.pantauJson', $ujian) }}', '{{ route('ujian.aksi', 'PID') }}', '{{ route('ujian.bukaKunci', 'PID') }}', '{{ route('ujian.log', [$ujian, 'PID']) }}')">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="nb-chip" style="background: var(--kuning)">Token: {{ $ujian->token }}</span>
            <span class="nb-chip" x-text="data.status"></span>
            <span class="nb-chip" style="background: var(--hijau)">Selesai <b x-text="hitung('selesai')"></b></span>
            <span class="nb-chip" style="background: var(--biru)">Mengerjakan <b x-text="hitung('berjalan')"></b></span>
            <span class="nb-chip">Belum <b x-text="hitung('belum')"></b></span>
            <span class="nb-chip" style="background: var(--merah)" x-show="terkunci() > 0" x-cloak>Terkunci <b x-text="terkunci()"></b></span>
            <span class="nb-chip" style="background: var(--merah)" x-show="galat" x-text="galat"></span>
            <a class="nb-btn putih" href="{{ route('ujian.hasil', $ujian) }}">Hasil</a>
        </div>
        <p class="text-sm mb-3">Diperbarui otomatis tiap 10 detik. "Keluar" = berapa kali siswa meninggalkan halaman ujian. Siswa yang melewati batas pelanggaran terkunci (atau dikumpulkan otomatis, sesuai pengaturan); tekan "Buka kunci" untuk memberinya jatah baru.</p>

        <div class="nb-scroll">
            <table class="nb-table">
                <thead><tr><th>Nama</th><th>Status</th><th>Terisi</th><th>Sisa</th><th>Keluar</th><th>Nilai</th><th></th></tr></thead>
                <tbody>
                    <template x-for="b in data.baris" :key="b.nama">
                        <tr :style="b.terkunci ? 'background: #ffd6d6' : ''">
                            <td x-text="b.nama"></td>
                            <td>
                                <span x-show="b.status === 'belum'">Belum</span>
                                <span x-show="b.status === 'berjalan'" class="font-bold">Mengerjakan</span>
                                <span x-show="b.status === 'selesai'" class="font-bold">Selesai</span>
                                <span x-show="b.terkunci" class="nb-chip" style="background: var(--merah)">Terkunci</span>
                            </td>
                            <td x-text="b.status === 'belum' ? '-' : b.terisi + '/' + data.total"></td>
                            <td x-text="menit(b.sisa)"></td>
                            <td :style="b.pelanggaran > 2 ? 'color:#b00020;font-weight:700' : ''" x-text="b.status === 'belum' ? '-' : b.pelanggaran"></td>
                            <td x-text="b.nilai === null ? '-' : b.nilai"></td>
                            <td class="whitespace-nowrap">
                                <button type="button" class="underline font-bold" x-show="b.terkunci" @click="bukaKunci(b.pid)">Buka kunci</button>
                                <button type="button" class="underline font-bold ml-2" x-show="b.status === 'berjalan'" @click="aksi(b.pid, 'tambah')">+5 mnt</button>
                                <button type="button" class="underline font-bold ml-2" x-show="b.status === 'berjalan'" @click="aksi(b.pid, 'akhiri', 'Kumpulkan paksa ujian siswa ini?')">Akhiri</button>
                                <a class="underline font-bold ml-2" x-show="b.pid" :href="log(b.pid)">Log</a>
                                <button type="button" class="underline font-bold ml-2" x-show="b.pid" @click="aksi(b.pid, 'reset', 'Reset? Jawaban siswa ini dihapus dan ia bisa mulai dari awal.')">Reset</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</x-guru-layout>
