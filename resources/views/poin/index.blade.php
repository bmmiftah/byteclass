@php
    $tgl = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale('id')->translatedFormat('j M Y') : '-';
    $semuaIds = collect($papan)->map(fn ($b) => (string) $b['siswa']->id)->values()->all();
@endphp

<x-guru-layout :judul="'Poin ' . $kelas->nama">
    <a href="{{ route('kelas.show', $kelas) }}" class="font-bold underline">&larr; {{ $kelas->nama }}</a>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3 mb-6">
        <h1 class="text-3xl">Poin &amp; Lencana {{ $kelas->nama }}</h1>
        @if ($p->aktif)
            <span class="nb-chip" style="background: var(--hijau)">Aktif sejak {{ $tgl($p->mulai_pada) }}</span>
        @else
            <span class="nb-chip">Belum aktif</span>
        @endif
    </div>

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

    @unless ($p->aktif)
        <div class="nb-card mb-6 space-y-3" style="background: var(--kuning)" id="aktifkan">
            <h2 class="text-xl">Gamifikasi belum dihidupkan untuk kelas ini</h2>
            <p>
                Siswa mendapat <b>poin usaha</b> otomatis dari hal yang memang mereka kerjakan: mengumpulkan tugas,
                menyelesaikan ujian, dan hadir. Ada lencana, papan tim (dari pembagian kelompok), dan target bersama
                satu kelas.
                Poin <b>tidak</b> dihitung dari nilai, jadi siswa yang sedang kesulitan tetap bisa maju.
            </p>
            <form method="POST" action="{{ route('poin.aktif', $kelas) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <input type="hidden" name="aktif" value="1">
                <div>
                    <label class="nb-label" for="mulai_pada">Hitung poin mulai tanggal</label>
                    <input class="nb-input" type="date" id="mulai_pada" name="mulai_pada" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
                </div>
                <button class="nb-btn hijau">Hidupkan gamifikasi</button>
            </form>
            <p class="text-sm">Aktivitas sebelum tanggal itu tidak dihitung. Mau ikut menghitung dari awal semester? Pilih tanggal awal semester.</p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3 mb-6">
            {{-- Papan siswa --}}
            <section class="lg:col-span-2 space-y-3">
                <h2 class="text-xl">Papan siswa</h2>
                <div class="nb-card overflow-x-auto p-0">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b-2 border-black" style="background: var(--kuning)">
                                <th class="p-2 w-12">#</th>
                                <th class="p-2">Nama</th>
                                <th class="p-2 text-right">Poin</th>
                                <th class="p-2 text-right">Lencana</th>
                                <th class="p-2">Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($papan as $b)
                                <tr class="border-b border-black">
                                    <td class="p-2">{{ $b['total'] > 0 ? $b['peringkat'] : '-' }}</td>
                                    <td class="p-2 font-bold">{{ $b['siswa']->nama }}</td>
                                    <td class="p-2 text-right"><b>{{ $b['total'] }}</b></td>
                                    <td class="p-2 text-right">{{ $b['lencana'] }}</td>
                                    <td class="p-2 text-sm">{{ $tgl($b['terakhir']) }}</td>
                                </tr>
                            @empty
                                <tr><td class="p-3" colspan="5">Belum ada siswa aktif di kelas ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="text-sm">
                    Poin otomatis diperbarui setiap halaman ini dibuka. Total tidak pernah di bawah 0.
                    Siswa <b>tidak</b> melihat papan ini; mereka hanya melihat poinnya sendiri
                    @if ($p->papan_individu) dan 5 besar @endif
                    .
                </p>
            </section>

            {{-- Target, tim, aturan --}}
            <aside class="space-y-6">
                <section class="nb-card space-y-2">
                    <h2 class="text-lg">Target kelas</h2>
                    @if ($target)
                        <p class="font-bold">{{ $target['judul'] ?: 'Target bersama' }}</p>
                        <div class="border-2 border-black bg-white h-6" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $target['persen'] }}">
                            <div class="h-full" style="width: {{ $target['persen'] }}%; background: {{ $target['tercapai'] ? 'var(--hijau)' : 'var(--biru)' }}"></div>
                        </div>
                        <p class="text-sm"><b>{{ $target['capai'] }}</b> dari {{ $target['target'] }} poin ({{ $target['persen'] }}%)</p>
                        @if ($target['tercapai'])
                            <p class="nb-chip" style="background: var(--hijau)">Tercapai!</p>
                        @endif
                        <form method="POST" action="{{ route('poin.mulaiBaru', $kelas) }}" onsubmit="return confirm('Mulai hitungan target dari nol (hari ini)? Poin siswa tidak berubah.')">
                            @csrf
                            <input type="hidden" name="untuk" value="target">
                            <button class="underline font-bold text-sm">Mulai target baru dari hari ini</button>
                        </form>
                    @else
                        <p class="text-sm">Belum ada target. Isi "Target bersama" di Pengaturan di bawah, misalnya 500 poin untuk nonton film bareng.</p>
                    @endif
                </section>

                <section class="nb-card space-y-2">
                    <h2 class="text-lg">Papan tim</h2>
                    @forelse ($tim as $t)
                        <div class="flex items-center justify-between gap-2 border-b border-black pb-1">
                            <p class="font-bold">{{ $t['peringkat'] }}. {{ $t['nama'] }}</p>
                            <p class="text-sm"><b>{{ number_format($t['rata'], 1, ',', '') }}</b> rata-rata &middot; {{ $t['total'] }} total &middot; {{ $t['jumlah'] }} orang</p>
                        </div>
                    @empty
                        <p class="text-sm">
                            @if ($sesis->isEmpty())
                                Belum ada pembagian kelompok. Buat dulu di menu Kelompok (Fase 2), lalu pilih di Pengaturan.
                            @else
                                Belum memakai tim. Pilih pembagian kelompok di Pengaturan di bawah.
                            @endif
                        </p>
                    @endforelse
                    @if ($tim)
                        <p class="text-xs">Skor tim = rata-rata poin per anggota sejak {{ $tgl($p->awalTim()) }}.</p>
                        <form method="POST" action="{{ route('poin.mulaiBaru', $kelas) }}" onsubmit="return confirm('Mulai putaran tim baru dari hari ini? Poin siswa tidak berubah, hanya skor tim yang mulai dari nol.')">
                            @csrf
                            <input type="hidden" name="untuk" value="tim">
                            <button class="underline font-bold text-sm">Mulai putaran baru dari hari ini</button>
                        </form>
                    @endif
                </section>

                <section class="nb-card space-y-1">
                    <h2 class="text-lg">Cara dapat poin</h2>
                    <ul class="text-sm space-y-1">
                        <li>Tugas dikumpulkan tepat waktu: <b>+{{ \App\Services\Gamifikasi::POIN['tugas_tepat'] }}</b></li>
                        <li>Tugas dikumpulkan terlambat: <b>+{{ \App\Services\Gamifikasi::POIN['tugas_telat'] }}</b></li>
                        <li>Menyelesaikan ujian: <b>+{{ \App\Services\Gamifikasi::POIN['ujian'] }}</b></li>
                        <li>Hadir (H) per hari: <b>+{{ \App\Services\Gamifikasi::POIN['hadir'] }}</b></li>
                        <li>Hadir penuh sepekan: <b>+{{ \App\Services\Gamifikasi::POIN['pekan'] }}</b></li>
                        <li>Dari guru: <b>-{{ \App\Services\Gamifikasi::MAKS_MANUAL }} sampai +{{ \App\Services\Gamifikasi::MAKS_MANUAL }}</b></li>
                    </ul>
                </section>
            </aside>
        </div>

        {{-- Beri poin --}}
        <form
            method="POST"
            action="{{ route('poin.beri', $kelas) }}"
            class="nb-card mb-6 space-y-4"
            id="beri-poin"
            x-data="{ pilih: @js(array_map('strval', (array) old('siswa_ids', []))), poin: {{ (int) old('poin', 5) }} }"
        >
            @csrf
            <h2 class="text-xl">Beri poin dari guru</h2>
            <p class="text-sm">Untuk perilaku baik yang tidak tercatat otomatis: aktif bertanya, membantu teman, dan sebagainya. Alasannya <b>dibaca siswa</b> di halaman Poin mereka.</p>
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="nb-label">Siswa (<span x-text="pilih.length"></span> dipilih)</span>
                    <button type="button" class="nb-btn putih" @click="pilih = @js($semuaIds)">Semua</button>
                    <button type="button" class="nb-btn putih" @click="pilih = []">Kosongkan</button>
                    @foreach ($tim as $t)
                        <button type="button" class="nb-btn putih" @click="pilih = @js(array_map('strval', array_column($t['anggota'], 'id')))">{{ $t['nama'] }}</button>
                    @endforeach
                </div>
                <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                    @foreach ($papan as $b)
                        <label class="flex items-center gap-2 border-2 border-black bg-white p-2 cursor-pointer">
                            <input type="checkbox" name="siswa_ids[]" value="{{ $b['siswa']->id }}" x-model="pilih" class="w-5 h-5">
                            <span class="truncate">{{ $b['siswa']->nama }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="nb-label" for="poin">Poin</label>
                    <input class="nb-input" type="number" id="poin" name="poin" x-model.number="poin" min="-{{ \App\Services\Gamifikasi::MAKS_MANUAL }}" max="{{ \App\Services\Gamifikasi::MAKS_MANUAL }}" required>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach ([1, 2, 5, 10, -5] as $n)
                            <button type="button" class="nb-btn putih" @click="poin = {{ $n }}">{{ $n > 0 ? '+' : '' }}{{ $n }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="nb-label" for="keterangan">Alasan</label>
                    <input class="nb-input" id="keterangan" name="keterangan" maxlength="150" list="alasan-umum" value="{{ old('keterangan') }}" required>
                    <datalist id="alasan-umum">
                        <option value="Aktif bertanya"></option>
                        <option value="Membantu teman"></option>
                        <option value="Presentasi dengan berani"></option>
                        <option value="Kerja sama tim yang baik"></option>
                        <option value="Koreksi poin (salah beri)"></option>
                    </datalist>
                </div>
            </div>
            <button class="nb-btn hijau">Simpan poin</button>
        </form>

        {{-- Pengaturan --}}
        <form method="POST" action="{{ route('poin.pengaturan', $kelas) }}" class="nb-card mb-6 space-y-4" id="pengaturan">
            @csrf
            <h2 class="text-xl">Pengaturan</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="nb-label" for="mulai_pada_p">Poin dihitung mulai tanggal</label>
                    <input class="nb-input" type="date" id="mulai_pada_p" name="mulai_pada" value="{{ old('mulai_pada', $p->mulai_pada->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required>
                    <p class="text-xs mt-1">Menggeser tanggal ini mundur akan memasukkan aktivitas lama pada kunjungan berikutnya.</p>
                </div>
                <label class="flex items-start gap-2 font-bold">
                    <input type="checkbox" name="papan_individu" value="1" class="w-5 h-5 mt-1" @checked(old('papan_individu', $p->papan_individu))>
                    <span>
                        Tampilkan 5 besar ke siswa
                        <span class="block text-sm font-normal">Mati bawaannya. Siswa lain tidak perlu melihat siapa yang paling bawah, jadi hanya 5 besar yang tampil.</span>
                    </span>
                </label>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="nb-label" for="kelompok_sesi_id">Papan tim dari pembagian kelompok</label>
                    <select class="nb-input" id="kelompok_sesi_id" name="kelompok_sesi_id">
                        <option value="">Tidak pakai tim</option>
                        @foreach ($sesis as $s)
                            <option value="{{ $s->id }}" @selected((string) old('kelompok_sesi_id', $p->kelompok_sesi_id) === (string) $s->id)>{{ $s->nama }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs mt-1">Memilih pembagian yang baru memulai putaran tim dari hari ini. Habis mengacak ulang kelompok, tekan "Mulai putaran baru".</p>
                </div>
                <div>
                    <label class="nb-label" for="tim_mulai">Putaran tim dimulai tanggal (opsional)</label>
                    <input class="nb-input" type="date" id="tim_mulai" name="tim_mulai" value="{{ old('tim_mulai', $p->tim_mulai?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}">
                </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="nb-label" for="target_poin">Target bersama (poin)</label>
                    <input class="nb-input" type="number" id="target_poin" name="target_poin" min="10" max="100000" value="{{ old('target_poin', $p->target_poin) }}" placeholder="kosong = tanpa target">
                </div>
                <div>
                    <label class="nb-label" for="target_judul">Hadiah / tujuan</label>
                    <input class="nb-input" id="target_judul" name="target_judul" maxlength="80" value="{{ old('target_judul', $p->target_judul) }}" placeholder="mis. Nonton film bareng">
                </div>
                <div>
                    <label class="nb-label" for="target_mulai">Target dihitung mulai (opsional)</label>
                    <input class="nb-input" type="date" id="target_mulai" name="target_mulai" value="{{ old('target_mulai', $p->target_mulai?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}">
                </div>
            </div>
            <button class="nb-btn">Simpan pengaturan</button>
        </form>

        {{-- Riwayat --}}
        <section class="mb-6 space-y-3">
            <h2 class="text-xl">Riwayat terbaru</h2>
            <div class="nb-card overflow-x-auto p-0">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b-2 border-black" style="background: var(--kuning)">
                            <th class="p-2">Tanggal</th>
                            <th class="p-2">Siswa</th>
                            <th class="p-2">Jenis</th>
                            <th class="p-2 text-right">Poin</th>
                            <th class="p-2">Alasan</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($riwayat as $r)
                            <tr class="border-b border-black">
                                <td class="p-2 text-sm whitespace-nowrap">{{ $tgl($r->terjadi_pada) }}</td>
                                <td class="p-2">{{ $r->siswa?->nama }}</td>
                                <td class="p-2 text-sm">{{ $r->label() }}</td>
                                <td class="p-2 text-right"><b>{{ $r->poin > 0 ? '+' : '' }}{{ $r->poin }}</b></td>
                                <td class="p-2 text-sm">{{ $r->keterangan }}</td>
                                <td class="p-2 text-right">
                                    @if ($r->manual())
                                        <form method="POST" action="{{ route('poin.hapus', $r) }}" onsubmit="return confirm('Batalkan pemberian poin ini?')">
                                            @csrf @method('DELETE')
                                            <button class="underline font-bold text-sm">Batalkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="p-3" colspan="6">Belum ada poin. Poin muncul saat siswa mengumpulkan tugas, menyelesaikan ujian, atau hadir.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-sm">Menampilkan 40 terbaru. Poin otomatis tidak bisa dihapus (akan muncul lagi); untuk mengoreksi, beri poin negatif beserta alasannya.</p>
        </section>

        <form method="POST" action="{{ route('poin.aktif', $kelas) }}" onsubmit="return confirm('Matikan gamifikasi? Siswa tidak lagi melihat halaman Poin. Poin yang sudah ada tetap tersimpan.')">
            @csrf
            <input type="hidden" name="aktif" value="0">
            <button class="font-bold underline">Matikan gamifikasi untuk kelas ini</button>
        </form>
    @endunless
</x-guru-layout>