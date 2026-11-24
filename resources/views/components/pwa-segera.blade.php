@php
    $akunSegera = request()->attributes->get('akun');
    $segera = $akunSegera ? \App\Services\PengingatSiswa::untuk($akunSegera->siswa) : collect();
@endphp

@if ($segera->isNotEmpty())
    <section class="mb-6" aria-label="Pengingat segera">
        <h2 class="text-xl mb-2">Segera!</h2>

        <div class="space-y-3">
            @foreach ($segera as $p)
                <a
                    href="{{ $p['url'] }}"
                    class="nb-card block"
                    style="background: {{ $p['mendesak'] ? 'var(--merah)' : 'var(--kuning)' }}"
                >
                    <p class="font-bold">{{ $p['judul'] }}</p>
                    <p>{{ $p['isi'] }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endif

{{-- Angka di ikon aplikasi (kalau didukung perangkat) --}}
<script>
    (function () {
        var n = {{ $segera->count() }};
        try {
            if (n > 0 && navigator.setAppBadge) {
                navigator.setAppBadge(n);
            } else if (navigator.clearAppBadge) {
                navigator.clearAppBadge();
            }
        } catch (e) {}
    })();
</script>