<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $denah->nama }} - {{ $denah->kelas->nama }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div x-data="tampilDenah(@js($data))">
        <div x-ref="area" class="bg-white p-4 md:p-8 min-h-screen flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-3xl md:text-4xl" x-text="denah.kelas + ' - ' + denah.nama"></h1>
                </div>

                <div class="flex flex-wrap gap-3 no-print">
                    <button
                        type="button"
                        class="nb-btn putih"
                        @click="balik = !balik"
                        x-text="balik ? 'Sudut pandang guru' : 'Sudut pandang siswa'"
                    ></button>
                    <button type="button" class="nb-btn putih" @click="layarPenuh()">Layar penuh</button>
                    <button type="button" class="nb-btn biru" onclick="window.print()">Cetak / PDF</button>
                </div>
            </div>

            <div class="flex-1 flex gap-4" :class="balik ? 'flex-col-reverse' : 'flex-col'">
                <div class="papan">PAPAN TULIS / DEPAN KELAS</div>

                <div class="nb-scroll">
                    <div class="kanvas polos besar mx-auto" :style="gayaKanvas">
                        <template x-for="(m, i) in tampil" :key="m.id">
                            <div class="meja muncul" :class="{ kosong: !nama(m) }" :style="gaya(m, i)">
                                <div><span x-text="nama(m)"></span></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>