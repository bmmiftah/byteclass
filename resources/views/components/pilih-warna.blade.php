@props(['terpilih' => null])

<fieldset>
    <legend class="nb-label">Warna kelas</legend>
    <div class="flex flex-wrap gap-3">
        @foreach (\App\Models\Kelas::WARNA as $nama => $hex)
            <label class="cursor-pointer">
                <input type="radio" name="warna" value="{{ $nama }}" class="sr-only" @checked(old('warna', $terpilih) === $nama)>
                <span class="nb-swatch" style="background: {{ $hex }}" title="{{ ucfirst($nama) }}"></span>
                <span class="sr-only">{{ ucfirst($nama) }}</span>
            </label>
        @endforeach
    </div>
</fieldset>