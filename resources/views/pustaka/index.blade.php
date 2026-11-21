<x-guru-layout judul="Pustaka">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-3xl">Pustaka Template</h1>
        <a class="nb-btn putih" href="{{ route('rubrik.index') }}">Kelola rubrik saya</a>
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

    <div x-data="{ tab: 'denah' }">
        <div class="flex gap-2 mb-6">
            <button
                type="button"
                class="nb-btn"
                :class="tab === 'denah' ? '' : 'putih'"
                @click="tab = 'denah'"
            >
                Template denah
            </button>

            <button
                type="button"
                class="nb-btn"
                :class="tab === 'rubrik' ? '' : 'putih'"
                @click="tab = 'rubrik'"
            >
                Rubrik
            </button>
        </div>

        {{-- ================= DENAH ================= --}}
        <div x-show="tab === 'denah'">
            <form
                method="POST"
                action="{{ route('pustaka.template.simpan') }}"
                class="nb-card mb-8 grid gap-4 md:grid-cols-5 items-end"
                style="background: var(--biru)"
            >
                @csrf

                <div class="md:col-span-2">
                    <label class="nb-label" for="denah_id">Simpan tata letak dari denah</label>
                    <select class="nb-input" id="denah_id" name="denah_id" required>
                        <option value="">- pilih denah -</option>
                        @foreach ($denahSaya as $d)
                            <option value="{{ $d->id }}">
                                {{ $d->kelas->nama }} &middot; {{ $d->nama }} ({{ $d->mejas_count }} meja)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="nb-label" for="nama_template">Nama template</label>
                    <input
                        class="nb-input"
                        id="nama_template"
                        name="nama"
                        maxlength="60"
                        required
                        placeholder="mis. Berkelompok 4 meja"
                    >
                </div>

                <button class="nb-btn putih">Simpan template</button>
            </form>

            <p class="text-sm -mt-5 mb-8">Yang disimpan hanya letak meja, bukan nama siswa.</p>

            <h2 class="text-xl mb-3">Template saya</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 mb-10">
                @forelse ($templateSaya as $t)
                    @include('pustaka._template', ['t' => $t, 'milikSendiri' => true])
                @empty
                    <div class="nb-card sm:col-span-2 lg:col-span-3">
                        <p>Belum ada template. Simpan dari salah satu denah di atas.</p>
                    </div>
                @endforelse
            </div>

            <h2 class="text-xl mb-3">Dari guru lain</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($templateBersama as $t)
                    @include('pustaka._template', ['t' => $t, 'milikSendiri' => false])
                @empty
                    <div class="nb-card sm:col-span-2 lg:col-span-3">
                        <p>Belum ada template yang dibagikan guru lain.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ================= RUBRIK ================= --}}
        <div x-show="tab === 'rubrik'" x-cloak>
            <h2 class="text-xl mb-3">Rubrik saya</h2>

            <div class="space-y-3 mb-10">
                @forelse ($rubrikSaya as $r)
                    <div class="nb-card flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-bold">
                                {{ $r->nama }}
                                <span
                                    class="nb-chip"
                                    style="background: {{ $r->dibagikan ? 'var(--hijau)' : '#fff' }}"
                                >
                                    {{ $r->dibagikan ? 'Dibagikan' : 'Pribadi' }}
                                </span>
                            </p>
                            <p class="text-sm">{{ $r->kriterias_count }} kriteria</p>
                        </div>

                        <div class="flex gap-2">
                            <a class="nb-btn putih" href="{{ route('rubrik.edit', $r) }}">Edit</a>

                            <form method="POST" action="{{ route('pustaka.rubrik.bagikan', $r) }}">
                                @csrf
                                <button class="nb-btn {{ $r->dibagikan ? 'putih' : '' }}">
                                    {{ $r->dibagikan ? 'Batal bagikan' : 'Bagikan' }}
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="nb-card">
                        <p>Belum ada rubrik. Buat dulu lewat "Kelola rubrik saya".</p>
                    </div>
                @endforelse
            </div>

            <h2 class="text-xl mb-3">Dari guru lain</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                @forelse ($rubrikBersama as $r)
                    <div class="nb-card">
                        <p class="font-bold text-lg">{{ $r->nama }}</p>
                        <p class="text-sm mb-2">oleh {{ $r->guru->name }}</p>

                        <ul class="text-sm list-disc pl-5 mb-3">
                            @foreach ($r->kriterias as $k)
                                <li>
                                    {{ $k->nama }}
                                    <span class="opacity-70">({{ $k->tipe }}, maks {{ $k->maks }}, bobot {{ $k->bobot }})</span>
                                </li>
                            @endforeach
                        </ul>

                        <form method="POST" action="{{ route('pustaka.rubrik.ambil', $r) }}">
                            @csrf
                            <button class="nb-btn">Salin ke rubrik saya</button>
                        </form>
                    </div>
                @empty
                    <div class="nb-card sm:col-span-2">
                        <p>Belum ada rubrik yang dibagikan guru lain.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-guru-layout>