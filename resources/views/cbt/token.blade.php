<x-cbt-layout judul="Token Ujian">
    <main class="max-w-md mx-auto p-4 py-10 text-center">
        <a href="{{ route('cbt.daftar') }}" class="font-bold underline block text-left mb-6">&larr; Kembali</a>
        <h1 class="text-3xl mb-2">{{ $ujian->judul }}</h1>
        <p class="mb-6">Durasi <b>{{ $ujian->durasi }} menit</b>. Waktu mulai berjalan setelah kamu menekan Mulai dan tidak bisa dihentikan.</p>

        {{-- Aturan ujian (Fase 9E): siswa berhak tahu apa yang dicatat --}}
        <div class="nb-card text-left mb-6" id="aturan-ujian">
            <p class="font-bold mb-1">Aturan ujian</p>
            <ul class="list-disc pl-5 space-y-1 text-sm">
                <li>Tetap di halaman ujian. Pindah tab atau aplikasi, atau menutup layar, dicatat dan bisa dilihat gurumu.</li>
                @if ($ujian->blokir_salin)
                    <li>Menyalin, menempel, memotong, dan klik kanan dinonaktifkan.</li>
                @endif
                @if ($ujian->layar_penuh)
                    <li>Ujian berjalan dalam layar penuh bila perangkatmu mendukung. Keluar dari layar penuh dicatat.</li>
                @endif
                @if ($ujian->punyaBatas())
                    <li>
                        Batas pelanggaran: <b>{{ $ujian->batas_pelanggaran }}</b>.
                        Setelah itu ujianmu {{ $ujian->aksi_batas === 'kumpul' ? 'dikumpulkan otomatis' : 'dikunci sampai gurumu membukanya' }}.
                    </li>
                @endif
                <li>Kerjakan sendiri. Jawaban antar siswa dibandingkan oleh gurumu.</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('cbt.mulai', $ujian) }}" class="nb-card space-y-4" style="background: var(--kuning)">
            @csrf
            <label class="nb-label text-left" for="token">Token ujian (dari gurumu)</label>
            <input class="nb-input text-center judul" style="font-size: 1.75rem; letter-spacing: .15em" id="token" name="token"
                   maxlength="8" autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false" required>
            @error('token') <p class="nb-error text-left">{{ $message }}</p> @enderror
            <button class="nb-btn w-full" style="font-size: 1.1rem">Mulai ujian</button>
        </form>
    </main>
</x-cbt-layout>
