{{-- Kartu menu: ikon berwarna + label. Warna ikon diwarisi dari --w milik induknya (.nb-menu-grid),
     atau bisa ditimpa lewat prop warna. --}}
@props(['href', 'ikon', 'label', 'warna' => null])
<a href="{{ $href }}" class="nb-menu"@if ($warna) style="--w: {{ $warna }}"@endif>
    <span class="nb-menu-ikon"><x-ikon :nama="$ikon" /></span>
    <span>{{ $label }}</span>
</a>
