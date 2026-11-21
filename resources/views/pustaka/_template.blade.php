@php
    $ada = collect($t->meja)->mapWithKeys(fn ($m) => [$m[0] . '-' . $m[1] => true]);
@endphp

<div class="nb-card">
    <div class="flex items-start justify-between gap-2 mb-2">
        <div>
            <p class="font-bold text-lg">{{ $t->nama }}</p>
            <p class="text-sm">
                {{ count($t->meja) }} meja &middot; {{ $t->lebar }}x{{ $t->tinggi }}
                @unless ($milikSendiri)
                    &middot; oleh {{ $t->guru->name }}
                @endunless
            </p>
        </div>

        @if ($milikSendiri)
            <span
                class="nb-chip"
                style="background: {{ $t->dibagikan ? 'var(--hijau)' : '#fff' }}"
            >
                {{ $t->dibagikan ? 'Dibagikan' : 'Pribadi' }}
            </span>
        @endif
    </div>

    {{-- Pratinjau kecil tata letak --}}
    <div
        class="grid gap-[3px] mb-3 overflow-x-auto"
        style="grid-template-columns: repeat({{ $t->lebar }}, 14px); width: max-content; max-width: 100%"
    >
        @for ($y = 0; $y < $t->tinggi; $y++)
            @for ($x = 0; $x < $t->lebar; $x++)
                <span
                    style="width: 14px; height: 14px; {{ isset($ada[$x . '-' . $y]) ? 'background: var(--ink)' : 'background: #eee' }}"
                ></span>
            @endfor
        @endfor
    </div>

    <form method="POST" action="{{ route('pustaka.template.pakai', $t) }}" class="space-y-2">
        @csrf

        <select class="nb-input" name="kelas_id" required aria-label="Pilih kelas">
            <option value="">- pakai di kelas -</option>
            @foreach ($kelasSaya as $k)
                <option value="{{ $k->id }}">{{ $k->nama }}</option>
            @endforeach
        </select>

        <input
            class="nb-input"
            name="nama"
            value="{{ $t->nama }}"
            maxlength="60"
            required
            aria-label="Nama denah baru"
        >

        <button class="nb-btn w-full">Buat denah dari template</button>
    </form>

    @if ($milikSendiri)
        <div class="flex gap-2 mt-3">
            <form method="POST" action="{{ route('pustaka.template.bagikan', $t) }}" class="flex-1">
                @csrf
                <button class="nb-btn putih w-full">
                    {{ $t->dibagikan ? 'Batal bagikan' : 'Bagikan' }}
                </button>
            </form>

            <form
                method="POST"
                action="{{ route('pustaka.template.hapus', $t) }}"
                onsubmit="return confirm('Hapus template ini?')"
            >
                @csrf
                @method('DELETE')
                <button class="nb-btn merah">Hapus</button>
            </form>
        </div>
    @endif
</div>