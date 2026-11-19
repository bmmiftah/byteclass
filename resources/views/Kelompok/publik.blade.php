<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $sesi->nama }} - {{ $sesi->kelas->nama }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div x-data="tampilKelompok(@js($data))">
        <div x-ref="area" class="bg-white p-4 md:p-8 min-h-screen tampil-besar">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <h1 class="text-3xl md:text-4xl" x-text="sesi.kelas + ' - ' + sesi.nama"></h1>

                <div class="flex flex-wrap gap-3 no-print">
                    <button type="button" class="nb-btn putih" @click="putar()">Putar ulang animasi</button>
                    <button type="button" class="nb-btn putih" @click="layarPenuh()">Layar penuh</button>
                    <button type="button" class="nb-btn biru" onclick="window.print()">Cetak / PDF</button>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <template x-for="(k, i) in daftar" :key="i">
                    <div class="nb-card" :style="'background:' + warna(i)">
                        <h2 class="text-2xl mb-3" x-text="k.nama"></h2>

                        <div class="zona">
                            <template x-for="a in k.anggota" :key="a.n">
                                <span
                                    class="chip-siswa"
                                    :class="{ ketua: a.nama === k.ketua }"
                                    :style="a.n < tahap ? '' : 'visibility:hidden'"
                                >
                                    <span x-text="a.nama"></span>
                                    <span class="tag" x-show="a.nama === k.ketua">ketua</span>
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</body>
</html>