<x-guru-layout judul="Beranda">
    <p class="text-sm">{{ $sekarang->locale('id')->translatedFormat('l, j F Y') }}</p>
    <h1 class="text-3xl mb-4">{{ $sapaan }}, {{ $guru->name }}</h1>

    @if (session('ok'))
        <div class="nb-card mb-4" style="background: var(--hijau)">{{ session('ok') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a class="nb-chip" href="{{ route('kelas.index') }}">{{ $ringkas['kelas'] }} kelas aktif</a>
        <span class="nb-chip">{{ $ringkas['siswa'] }} siswa</span>
        <span class="nb-chip">{{ $ringkas['tugas'] }} tugas berjalan</span>
        <span class="nb-chip" @if ($ringkas['ujianBuka']) style="background: var(--pink)" @endif>{{ $ringkas['ujianBuka'] }} ujian dibuka</span>
    </div>

    @if ($ringkas['kelas'] === 0)
        <div class="nb-card mb-6" id="kosong">
            <p class="font-bold">Belum ada kelas aktif.</p>
            <p class="text-sm mt-1">Buat kelas di menu Kelas, lalu beranda ini akan terisi.</p>
            <a class="nb-btn kuning inline-block mt-3" href="{{ route('kelas.index') }}">Ke daftar kelas</a>
        </div>
    @else
        <h2 class="text-2xl font-bold mb-3">Perlu dikerjakan</h2>

        @if ($perluTindakan === 0)
            <div class="nb-card mb-6" id="beres" style="background: var(--hijau)">
                <p class="font-bold">Semua beres.</p>
                <p class="text-sm">Tidak ada presensi, penilaian, koreksi, atau remedial yang menunggu.</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 mb-6">
                @if ($presensiBelum->count())
                    <div class="nb-card" id="kartu-presensi">
                        <p class="font-bold">Presensi hari ini belum diisi</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($presensiBelum as $k)
                                <li><a class="underline font-bold" href="{{ route('presensi.index', $k) }}">{{ $k->nama }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($perluDinilai->count())
                    <div class="nb-card" id="kartu-dinilai">
                        <p class="font-bold">Tugas menunggu dinilai</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($perluDinilai->take(8) as $x)
                                <li>
                                    <a class="underline font-bold" href="{{ route('tugas.hasil', $x['tugas']->id) }}">{{ $x['tugas']->judul }}</a>
                                    <span class="text-sm">{{ $x['kelas'] }}</span>
                                    <span class="nb-chip" style="background: var(--oranye)">{{ $x['jumlah'] }} belum</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($perluDinilai->count() > 8)
                            <p class="text-sm mt-1">dan {{ $perluDinilai->count() - 8 }} tugas lain</p>
                        @endif
                    </div>
                @endif

                @if ($perluKoreksi->count())
                    <div class="nb-card" id="kartu-koreksi">
                        <p class="font-bold">Uraian menunggu koreksi</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($perluKoreksi->take(8) as $x)
                                <li>
                                    <a class="underline font-bold" href="{{ route('ujian.hasil', $x['ujian']->id) }}">{{ $x['ujian']->judul }}</a>
                                    <span class="text-sm">{{ $x['kelas'] }}</span>
                                    <span class="nb-chip" style="background: var(--oranye)">{{ $x['jumlah'] }} siswa</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($remedial->count())
                    <div class="nb-card" id="kartu-remedial">
                        <p class="font-bold">Remedial belum dibuat</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($remedial->take(8) as $x)
                                <li>
                                    <a class="underline font-bold" href="{{ route('analisis.ujian', $x['ujian']->id) }}#remedial">{{ $x['ujian']->judul }}</a>
                                    <span class="text-sm">{{ $x['kelas'] }}</span>
                                    <span class="nb-chip" style="background: var(--merah)">{{ $x['jumlah'] }} di bawah KKM {{ $x['kkm'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        @if ($ujianBuka->count() || $ujianAkan->count() || $tugasBerjalan->count())
            <h2 class="text-2xl font-bold mb-3">Sedang berlangsung</h2>
            <div class="grid gap-4 md:grid-cols-2 mb-6">
                @if ($ujianBuka->count() || $ujianAkan->count())
                    <div class="nb-card" id="kartu-ujian">
                        <p class="font-bold">Ujian</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($ujianBuka as $x)
                                <li>
                                    <a class="underline font-bold" href="{{ route('ujian.pantau', $x['ujian']->id) }}">{{ $x['ujian']->judul }}</a>
                                    <span class="text-sm">{{ $x['kelas'] }}</span>
                                    <span class="nb-chip" style="background: var(--pink)">Dibuka</span>
                                    <span class="text-sm">{{ $x['ujian']->berjalan }} mengerjakan, {{ $x['ujian']->selesai }} selesai dari {{ $x['sasaran'] }} siswa</span>
                                </li>
                            @endforeach
                            @foreach ($ujianAkan as $x)
                                <li>
                                    <a class="underline" href="{{ route('ujian.edit', $x['ujian']->id) }}">{{ $x['ujian']->judul }}</a>
                                    <span class="text-sm">{{ $x['kelas'] }}, buka {{ $x['ujian']->buka_pada->locale('id')->translatedFormat('D, j M H.i') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($tugasBerjalan->count())
                    <div class="nb-card" id="kartu-tugas">
                        <p class="font-bold">Tugas berjalan</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($tugasBerjalan->take(8) as $x)
                                <li>
                                    <a class="underline font-bold" href="{{ route('tugas.hasil', $x['tugas']->id) }}">{{ $x['tugas']->judul }}</a>
                                    <span class="text-sm">{{ $x['kelas'] }}, tenggat {{ $x['tugas']->tenggatTampil() }}</span>
                                    <span class="nb-chip">{{ $x['tugas']->pengumpulans_count }} dari {{ $x['sasaran'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <h2 class="text-2xl font-bold mb-3">7 hari ke depan</h2>
        <div class="nb-card mb-6" id="kartu-agenda">
            @forelse ($agenda->groupBy('tanggal') as $tgl => $acara)
                <p class="font-bold mt-2 first:mt-0">
                    {{ \Illuminate\Support\Carbon::parse($tgl)->locale('id')->translatedFormat('l, j F') }}
                    @if ($tgl === $sekarang->format('Y-m-d'))
                        <span class="nb-chip" style="background: var(--kuning)">Hari ini</span>
                    @endif
                </p>
                <ul class="text-sm">
                    @foreach ($acara as $e)
                        <li>
                            <span class="nb-chip" style="background: {{ $e['warna'] }}">{{ $e['label'] }}</span>
                            {{ $e['jam'] ? $e['jam'] . ' ' : '' }}{{ $e['judul'] }} <span class="text-xs">({{ $e['kelas'] }})</span>
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="text-sm">Tidak ada tenggat, ujian, atau agenda dalam 7 hari ke depan.</p>
            @endforelse
        </div>

        @if ($siswaAlpa->count() || $jurnalBelum)
            <h2 class="text-2xl font-bold mb-3">Perlu perhatian</h2>
            <div class="grid gap-4 md:grid-cols-2 mb-6">
                @if ($siswaAlpa->count())
                    <div class="nb-card" id="kartu-alpa">
                        <p class="font-bold">Sering alpa ({{ \App\Services\BerandaGuru::BATAS_ALPA }} kali atau lebih dalam {{ \App\Services\BerandaGuru::JENDELA_ALPA }} hari)</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($siswaAlpa->take(10) as $x)
                                <li>{{ $x['siswa']->nama }} <span class="text-sm">{{ $x['kelas'] }}</span> <span class="nb-chip" style="background: var(--merah)">{{ $x['jumlah'] }}x alpa</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($jurnalBelum)
                    <div class="nb-card" id="kartu-jurnal">
                        <p class="font-bold">Jurnal mengajar hari ini belum ditulis</p>
                        <a class="nb-btn putih inline-block mt-2" href="{{ route('jurnal.index') }}">Tulis jurnal</a>
                    </div>
                @endif
            </div>
        @endif
    @endif
</x-guru-layout>