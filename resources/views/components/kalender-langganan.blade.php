@php
    $akunKal = request()->attributes->get('akun');
    $tautan = $akunKal?->tautanKalender();
    $webcal = $tautan ? preg_replace('#^https?://#', 'webcal://', $tautan) : null;
@endphp

<section id="langganan" class="nb-card mt-8" style="background: #fff" x-data="{ salin: false }">
    <h2 class="text-xl mb-1">Kalender di HP</h2>
    <p class="mb-3">Tenggat tugas, jadwal ujian, dan agenda kelas bisa masuk ke Google Calendar atau kalender HP lu, lengkap dengan pengingat dari aplikasinya sendiri.</p>

    @if (session('ok'))
        <p class="nb-card mb-3" style="background: var(--hijau)">{{ session('ok') }}</p>
    @endif

    @if (! $tautan)
        <form method="POST" action="{{ route('saya.kalender.langganan') }}">
            @csrf
            <button class="nb-btn hijau">Aktifkan langganan kalender</button>
        </form>
    @else
        <label class="nb-label" for="tautan-kal">Tautan kalender lu (rahasia, jangan dibagikan)</label>
        <div class="flex flex-wrap gap-2 mb-3">
            <input id="tautan-kal" class="nb-input" style="flex: 1; min-width: 12rem" readonly value="{{ $tautan }}" onfocus="this.select()">
            <button
                type="button"
                class="nb-btn"
                @click="navigator.clipboard.writeText(document.getElementById('tautan-kal').value).then(() => { salin = true; setTimeout(() => salin = false, 2500) }).catch(() => document.getElementById('tautan-kal').select())"
                x-text="salin ? 'Tersalin!' : 'Salin'"
            >Salin</button>
        </div>

        <details class="mb-3">
            <summary class="font-bold cursor-pointer">Cara memasang</summary>
            <div class="mt-2 space-y-2 text-sm">
                <p><b>Google Calendar (Android / komputer):</b> buka calendar.google.com di browser, di kiri pilih <b>Kalender lain</b> (tanda +), <b>Dari URL</b>, tempel tautan di atas, lalu <b>Tambahkan kalender</b>. Di HP Android kalendernya muncul sendiri beberapa saat kemudian.</p>
                <p><b>iPhone:</b> <a class="underline font-bold" href="{{ $webcal }}">ketuk di sini</a>, lalu pilih Berlangganan. Atau: Pengaturan, Kalender, Akun, Tambah Akun, Lainnya, Tambah Kalender Berlangganan, tempel tautannya.</p>
                <p>Pembaruan tidak instan: Google Calendar bisa butuh beberapa jam untuk memperbarui, jadi tugas yang baru dibuat guru mungkin muncul agak telat. Untuk yang mendadak, cek Dashboard Saya.</p>
            </div>
        </details>

        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('saya.kalender.langganan.ulang') }}" onsubmit="return confirm('Buat tautan baru? Tautan lama langsung mati dan harus dipasang ulang.')">
                @csrf
                <button class="nb-btn putih">Buat tautan baru</button>
            </form>
            <form method="POST" action="{{ route('saya.kalender.langganan.mati') }}" onsubmit="return confirm('Matikan langganan kalender?')">
                @csrf
                @method('DELETE')
                <button class="nb-btn putih">Matikan</button>
            </form>
        </div>
    @endif
</section>