{{-- Pengaturan anti-curang (Fase 9E). Dipasang di halaman edit ujian, di luar form utama. --}}
<form method="POST" action="{{ route('ujian.anticurang', $ujian) }}" class="nb-card space-y-4 mt-6" id="kartu-anticurang">
    @csrf
    <h2 class="text-lg">Anti-curang</h2>
    <p class="text-sm">
        Pindah tab, pindah aplikasi, dan keluar layar penuh selalu dicatat dengan waktu, dan siswa diberi tahu aturannya sebelum mulai.
        Fitur ini mempersulit dan mencatat, bukan menjamin: foto layar dengan HP lain tidak bisa dicegah dari browser.
    </p>

    <label class="flex items-start gap-2 font-bold">
        <input type="checkbox" name="blokir_salin" value="1" class="w-5 h-5 mt-1" @checked(old('blokir_salin', $ujian->blokir_salin))>
        <span>Blokir salin, tempel, potong, klik kanan, dan cetak
            <span class="block text-sm font-normal">Percobaannya dicatat di log.</span></span>
    </label>

    <label class="flex items-start gap-2 font-bold">
        <input type="checkbox" name="layar_penuh" value="1" class="w-5 h-5 mt-1" @checked(old('layar_penuh', $ujian->layar_penuh))>
        <span>Wajib layar penuh
            <span class="block text-sm font-normal">Soal tertutup sampai siswa masuk layar penuh. Tidak berlaku di iPhone (Safari tidak mendukungnya), di sana dilewati tanpa menghalangi siswa.</span></span>
    </label>

    <div class="grid gap-3 md:grid-cols-2">
        <div>
            <label class="nb-label" for="batas_pelanggaran">Batas pelanggaran</label>
            <input class="nb-input" type="number" min="1" max="50" id="batas_pelanggaran" name="batas_pelanggaran"
                   value="{{ old('batas_pelanggaran', $ujian->batas_pelanggaran) }}" placeholder="kosong = tanpa batas">
            @error('batas_pelanggaran') <p class="nb-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="nb-label" for="aksi_batas">Kalau batas tercapai</label>
            <select class="nb-input" id="aksi_batas" name="aksi_batas">
                <option value="kunci" @selected(old('aksi_batas', $ujian->aksi_batas) === 'kunci')>Kunci ujian (guru membuka)</option>
                <option value="kumpul" @selected(old('aksi_batas', $ujian->aksi_batas) === 'kumpul')>Kumpulkan otomatis</option>
            </select>
        </div>
    </div>
    <p class="text-sm">
        Satu "pelanggaran" = pindah tab atau aplikasi, jendela tidak aktif, atau keluar layar penuh.
        Dua kejadian dalam 3 detik dihitung satu. Salin atau tempel hanya dicatat, tidak dihitung.
    </p>

    <button class="nb-btn kuning">Simpan anti-curang</button>
</form>
