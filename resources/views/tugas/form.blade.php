<x-guru-layout :judul="$tugas->exists ? 'Ubah tugas' : 'Tugas baru'">
    <a href="{{ $tugas->exists ? route('tugas.hasil', $tugas) : route('tugas.index', $kelas) }}" class="font-bold underline">
        &larr; Kembali
    </a>

    <h1 class="text-3xl mt-3 mb-6">{{ $tugas->exists ? 'Ubah tugas' : 'Tugas baru' }} &middot; {{ $kelas->nama }}</h1>

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    <form
        method="POST"
        action="{{ $tugas->exists ? route('tugas.update', $tugas) : route('tugas.store', $kelas) }}"
        class="space-y-4"
        x-data="{ jenis: '{{ old('jenis', $tugas->jenis) }}' }"
    >
        @csrf
        @if ($tugas->exists)
            @method('PUT')
        @endif

        <div class="nb-card grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="nb-label" for="judul">Judul tugas</label>
                <input
                    class="nb-input"
                    id="judul"
                    name="judul"
                    maxlength="150"
                    value="{{ old('judul', $tugas->judul) }}"
                    required
                >
            </div>

            <div>
                <label class="nb-label" for="tenggat">Tenggat</label>
                <input
                    class="nb-input"
                    type="datetime-local"
                    id="tenggat"
                    name="tenggat"
                    value="{{ old('tenggat', $tugas->tenggat?->format('Y-m-d\TH:i')) }}"
                    required
                >
            </div>

            <div>
                <label class="nb-label" for="mapel_id">Mapel</label>
                <select class="nb-input" id="mapel_id" name="mapel_id">
                    <option value="">-</option>
                    @foreach ($mapels as $m)
                        <option value="{{ $m->id }}" @selected((int) old('mapel_id', $tugas->mapel_id) === $m->id)>
                            {{ $m->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="nb-label" for="telat">Kalau lewat tenggat</label>
                <select class="nb-input" id="telat" name="telat">
                    <option value="tutup" @selected(old('telat', $tugas->telat) === 'tutup')>
                        Tutup, tidak bisa mengumpulkan
                    </option>
                    <option value="terima" @selected(old('telat', $tugas->telat) === 'terima')>
                        Tetap diterima, diberi label telat
                    </option>
                </select>
            </div>

            <div>
                <label class="nb-label" for="terbit_pada">Jadwal tayang (opsional)</label>
                <input
                    class="nb-input"
                    type="datetime-local"
                    id="terbit_pada"
                    name="terbit_pada"
                    value="{{ old('terbit_pada', $tugas->terbit_pada?->format('Y-m-d\TH:i')) }}"
                >
                <p class="text-sm">Diisi = tugas otomatis terbit dan muncul di siswa pada jam ini (waktu WIB). Kosong = jadi draf, terlihat setelah lu klik Terbitkan.</p>
            </div>
        </div>

        <div class="nb-card">
            <label class="nb-label mb-2">Instruksi untuk siswa</label>
            <x-editor-teks
                nama="instruksi"
                :nilai="old('instruksi', $tugas->instruksi)"
                petunjuk="Tulis apa yang harus dikerjakan, boleh pakai gambar atau tabel..."
            />
        </div>

        <div class="nb-card grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="nb-label" for="jenis">Cara mengumpulkan</label>
                <select class="nb-input" id="jenis" name="jenis" x-model="jenis">
                    @foreach (\App\Models\Tugas::JENIS as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <template x-if="jenis === 'file'">
                <div class="md:col-span-2 grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="nb-label" for="jenis_berkas">Jenis file</label>
                        <select class="nb-input" id="jenis_berkas" name="jenis_berkas">
                            @foreach (['umum' => 'Foto dan dokumen', 'gambar' => 'Foto saja', 'dokumen' => 'Dokumen saja'] as $k => $v)
                                <option value="{{ $k }}" @selected(old('jenis_berkas', $tugas->jenis_berkas) === $k)>
                                    {{ $v }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="nb-label" for="maks_berkas">Maks. jumlah file</label>
                        <input
                            class="nb-input"
                            type="number"
                            id="maks_berkas"
                            name="maks_berkas"
                            min="1"
                            max="10"
                            value="{{ old('maks_berkas', $tugas->maks_berkas) }}"
                        >
                    </div>

                    <div>
                        <label class="nb-label" for="ukuran_mb">Maks. ukuran per file (MB)</label>
                        <input
                            class="nb-input"
                            type="number"
                            id="ukuran_mb"
                            name="ukuran_mb"
                            min="1"
                            max="50"
                            value="{{ old('ukuran_mb', $tugas->ukuran_mb) }}"
                        >
                    </div>
                </div>
            </template>

            {{-- Nilai bawaan agar validasi tetap lolos untuk jenis lain --}}
            <template x-if="jenis !== 'file'">
                <div class="hidden">
                    <input type="hidden" name="jenis_berkas" value="{{ old('jenis_berkas', $tugas->jenis_berkas) }}">
                    <input type="hidden" name="maks_berkas" value="{{ old('maks_berkas', $tugas->maks_berkas) }}">
                    <input type="hidden" name="ukuran_mb" value="{{ old('ukuran_mb', $tugas->ukuran_mb) }}">
                </div>
            </template>

            <template x-if="jenis === 'eksternal'">
                <div class="md:col-span-2 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="nb-label" for="tautan_eksternal">Link folder pengumpulan</label>
                        <input
                            class="nb-input"
                            id="tautan_eksternal"
                            name="tautan_eksternal"
                            placeholder="https://..."
                            value="{{ old('tautan_eksternal', $tugas->tautan_eksternal) }}"
                        >
                    </div>

                    <div>
                        <label class="nb-label" for="kode_konfirmasi">Kode konfirmasi (opsional)</label>
                        <input
                            class="nb-input"
                            id="kode_konfirmasi"
                            name="kode_konfirmasi"
                            maxlength="20"
                            value="{{ old('kode_konfirmasi', $tugas->kode_konfirmasi) }}"
                            placeholder="mis. K7M2"
                        >
                        <p class="text-sm">Taruh kode ini di nama folder. Siswa harus mengetiknya untuk konfirmasi.</p>
                    </div>
                </div>
            </template>

            <label class="flex items-center gap-2 font-bold md:col-span-2">
                <input
                    type="checkbox"
                    name="boleh_ulang"
                    value="1"
                    @checked(old('boleh_ulang', $tugas->boleh_ulang))
                >
                Siswa boleh mengumpulkan ulang (selama belum dinilai)
            </label>
        </div>

        <button class="nb-btn kuning">{{ $tugas->exists ? 'Simpan perubahan' : 'Simpan sebagai draf' }}</button>
    </form>
</x-guru-layout>