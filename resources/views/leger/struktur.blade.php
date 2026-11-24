<x-guru-layout :judul="'Atur bab ' . $kelas->nama">
    <a href="{{ route('leger.bab', [$kelas, 'mapel' => $mapel]) }}" class="font-bold underline">&larr; Leger per bab</a>
    <h1 class="text-3xl mt-3 mb-2">Atur Bab dan Slot {{ $kelas->nama }}</h1>
    <p class="mb-6">Susun bab satu semester, tentukan slot penilaian tiap aspek, lalu pilih penilaian (termasuk tugas dan ujian yang sudah dikirim nilainya) yang mengisi tiap slot.</p>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    @if ($errors->any())
        <div class="nb-card mb-4" style="background: var(--merah)">
            @foreach ($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    @php
        // satu pilihan penilaian; $dipilih = id yang terpilih di slot ini
        $opsi = function ($dipilih) use ($penilaians, $asal, $dipakai) {
            $h = '<option value="">- belum dipilih -</option>';
            foreach ($penilaians as $p) {
                $lain = isset($dipakai[$p->id]) && (int) $dipilih !== $p->id;
                $h .= '<option value="' . $p->id . '"' . ((int) $dipilih === $p->id ? ' selected' : '') . ($lain ? ' disabled' : '') . '>'
                    . e($p->judul) . ' (' . $asal[$p->id] . ')' . ($lain ? ' - dipakai: ' . e($dipakai[$p->id]) : '') . '</option>';
            }

            return new \Illuminate\Support\HtmlString($h);
        };
    @endphp

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label class="nb-label" for="mapel">Mapel</label>
            <select class="nb-input" style="width: 16rem" id="mapel" name="mapel" onchange="this.form.submit()">
                @foreach ($daftarMapel as $id => $nama)
                    <option value="{{ $id }}" @selected($id === $mapel)>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button class="nb-btn putih">Tampilkan</button></noscript>
    </form>

    @if (! count($daftarMapel))
        <div class="nb-card"><p class="font-bold">Belum ada penilaian di kelas ini.</p></div>
    @else
        <div class="nb-card mb-6" id="kotak-mode">
            <p class="font-bold mb-1">Mode rekap</p>
            @if ($set['mode'] === 'bab')
                <p class="text-sm">Sedang memakai <b>rekap per bab</b> untuk mapel ini.</p>
            @else
                <p class="text-sm mb-2">Mapel ini masih memakai <b>mode kategori</b> (Fase 9A). Nyalakan mode per bab kalau susunan bab sudah siap. Data penilaian tidak berubah.</p>
                <form method="POST" action="{{ route('leger.mode', $kelas) }}">
                    @csrf
                    <input type="hidden" name="mapel_id" value="{{ $mapel }}">
                    <input type="hidden" name="mode" value="bab">
                    <button class="nb-btn kuning" id="nyalakan-bab">Nyalakan rekap per bab</button>
                </form>
            @endif
        </div>

        <form method="POST" action="{{ route('leger.ptspas', $kelas) }}" class="nb-card mb-6" id="form-ptspas">
            @csrf
            <input type="hidden" name="mapel_id" value="{{ $mapel }}">
            <p class="font-bold mb-1">KKM, PTS, dan PAS</p>
            <p class="text-sm mb-3">PTS dan PAS opsional dan berdiri di luar bab. Kalau dipilih, keduanya ikut membentuk nilai <b>Pengetahuan</b> bersama rata-rata n-KD. Kosongkan kalau mapel ini tidak punya PTS atau PAS.</p>
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="nb-label" for="kkm">KKM (satu untuk semua)</label>
                    <input class="nb-input" id="kkm" name="kkm" type="number" min="0" max="100" value="{{ old('kkm', $set['kkm']) }}">
                </div>
                <div></div>
                <div>
                    <label class="nb-label" for="pts">Penilaian untuk PTS</label>
                    <select class="nb-input" id="pts" name="pts_penilaian_id">{{ $opsi(old('pts_penilaian_id', $set['pts_id'])) }}</select>
                </div>
                <div>
                    <label class="nb-label" for="pas">Penilaian untuk PAS</label>
                    <select class="nb-input" id="pas" name="pas_penilaian_id">{{ $opsi(old('pas_penilaian_id', $set['pas_id'])) }}</select>
                </div>
                <div>
                    <label class="nb-label" for="bobot_nkd">Bobot rata-rata n-KD</label>
                    <input class="nb-input" id="bobot_nkd" name="bobot_nkd" type="number" min="0" max="100" value="{{ old('bobot_nkd', $set['bobot_nkd']) }}">
                </div>
                <div>
                    <label class="nb-label" for="bobot_pts">Bobot PTS</label>
                    <input class="nb-input" id="bobot_pts" name="bobot_pts" type="number" min="0" max="100" value="{{ old('bobot_pts', $set['bobot_pts']) }}">
                </div>
                <div>
                    <label class="nb-label" for="bobot_pas">Bobot PAS</label>
                    <input class="nb-input" id="bobot_pas" name="bobot_pas" type="number" min="0" max="100" value="{{ old('bobot_pas', $set['bobot_pas']) }}">
                </div>
            </div>
            <p class="text-sm mt-2">Kalau PTS atau PAS tidak dipilih (atau siswanya belum bernilai), bobotnya dilewati dan bobot yang lain dihitung ulang.</p>
            <button class="nb-btn kuning mt-3" id="simpan-ptspas">Simpan</button>
        </form>

        @foreach ($babs as $b)
            @php $slotAspek = fn ($a) => $b->slots->where('aspek', $a); @endphp
            <div class="nb-card mb-6" id="bab-{{ $b->id }}">
                <form method="POST" action="{{ route('leger.bab.simpan', $b) }}">
                    @csrf
                    <div class="flex flex-wrap items-end gap-3 mb-3">
                        <div class="grow">
                            <label class="nb-label" for="nama-{{ $b->id }}">Nama bab {{ $loop->iteration }}</label>
                            <input class="nb-input" id="nama-{{ $b->id }}" name="nama" value="{{ $b->nama }}" required maxlength="150">
                        </div>
                        <label class="text-sm font-bold"><input type="checkbox" name="pengetahuan" value="1" @checked($b->pengetahuan)> Pengetahuan</label>
                        <label class="text-sm font-bold"><input type="checkbox" name="keterampilan" value="1" @checked($b->keterampilan)> Keterampilan</label>
                    </div>

                    @foreach ($aspek as $kode => $label)
                        @php $jumlahBobot = $slotAspek($kode)->sum('bobot'); @endphp
                        <div class="mb-4 aspek-{{ $kode }}" @if (! $b->{$kode}) style="opacity: .5" @endif>
                            <p class="font-bold mb-1">
                                {{ $label }}
                                @if ($slotAspek($kode)->count() && $jumlahBobot !== 100)
                                    <span class="nb-chip" style="background: var(--oranye)">bobot slot berjumlah {{ $jumlahBobot }} (sebaiknya 100)</span>
                                @endif
                            </p>
                            <div class="nb-scroll">
                                <table class="nb-table">
                                    <thead>
                                        <tr>
                                            <th>Nama slot</th>
                                            <th>Bobot</th>
                                            <th>Diisi oleh penilaian</th>
                                            <th>Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($slotAspek($kode) as $s)
                                            <tr>
                                                <td><input class="nb-input" name="slot[{{ $s->id }}][nama]" value="{{ $s->nama }}" maxlength="80"></td>
                                                <td><input class="nb-input" style="width: 5rem" type="number" min="1" max="100" name="slot[{{ $s->id }}][bobot]" value="{{ $s->bobot }}"></td>
                                                <td><select class="nb-input" name="slot[{{ $s->id }}][penilaian_id]">{{ $opsi($s->penilaian_id) }}</select></td>
                                                <td><input type="checkbox" name="slot[{{ $s->id }}][hapus]" value="1"></td>
                                            </tr>
                                        @endforeach
                                        @for ($i = 0; $i < 2; $i++)
                                            <tr>
                                                <td><input class="nb-input" name="baru[{{ $kode }}][{{ $i }}][nama]" placeholder="slot baru, mis. UH" maxlength="80"></td>
                                                <td><input class="nb-input" style="width: 5rem" type="number" min="1" max="100" name="baru[{{ $kode }}][{{ $i }}][bobot]" value="100"></td>
                                                <td><select class="nb-input" name="baru[{{ $kode }}][{{ $i }}][penilaian_id]">{{ $opsi(null) }}</select></td>
                                                <td></td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <button class="nb-btn kuning">Simpan bab</button>
                </form>

                <div class="flex flex-wrap gap-4 mt-4">
                    @if ($babs->count() > 1)
                        <form method="POST" action="{{ route('leger.bab.salin', $b) }}" class="text-sm" onsubmit="return confirm('Susunan slot bab tujuan akan diganti. Lanjut?')">
                            @csrf
                            <p class="font-bold">Salin susunan slot ke bab lain</p>
                            @foreach ($babs as $t)
                                @if ($t->id !== $b->id)
                                    <label class="mr-3"><input type="checkbox" name="target[]" value="{{ $t->id }}"> {{ $t->nama }}</label>
                                @endif
                            @endforeach
                            <button class="nb-btn putih mt-2">Salin</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('leger.bab.hapus', $b) }}" onsubmit="return confirm('Hapus bab ini beserta slotnya? Nilai di Penilaian tidak terhapus.')">
                        @csrf
                        <button class="nb-btn merah">Hapus bab</button>
                    </form>
                </div>
            </div>
        @endforeach

        <form method="POST" action="{{ route('leger.bab.tambah', $kelas) }}" class="nb-card flex flex-wrap items-end gap-3 mb-8" id="form-bab-baru">
            @csrf
            <input type="hidden" name="mapel_id" value="{{ $mapel }}">
            <div class="grow">
                <label class="nb-label" for="nama-baru">Tambah bab</label>
                <input class="nb-input" id="nama-baru" name="nama" placeholder="mis. Konsep Design Thinking dalam rancangan UX Design" required maxlength="150">
            </div>
            <button class="nb-btn kuning" id="tambah-bab">+ Bab</button>
        </form>
    @endif
</x-guru-layout>