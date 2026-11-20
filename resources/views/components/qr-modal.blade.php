@props(['url', 'nama' => 'qr', 'judul' => 'QR', 'tombol' => 'Tampilkan QR'])

<div
    class="inline-block"
    x-data="{
        buka: false,
        tampil() {
            this.buka = true;
            this.$nextTick(() => QRCode.toCanvas(this.$refs.kanvas, '{{ $url }}', { width: 280, margin: 2 }));
        },
        unduh() {
            const a = document.createElement('a');
            a.href = this.$refs.kanvas.toDataURL('image/png');
            a.download = '{{ $nama }}.png';
            a.click();
        }
    }"
    @keydown.escape.window="buka = false"
>
    <button type="button" class="nb-btn putih" @click="tampil()">{{ $tombol }}</button>

    <div
        x-show="buka"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,.45); z-index: 60"
        @click.self="buka = false"
    >
        <div
            class="nb-card text-center"
            style="background: #fff; max-width: 24rem; width: 100%"
            role="dialog"
            aria-modal="true"
        >
            <h2 class="text-xl mb-3">{{ $judul }}</h2>
            <canvas x-ref="kanvas" class="mx-auto border-2 border-black"></canvas>
            <p class="text-xs break-all mt-3">{{ $url }}</p>

            <div class="flex justify-center gap-2 mt-4">
                <button type="button" class="nb-btn" @click="unduh()">Unduh QR</button>
                <button type="button" class="nb-btn putih" @click="buka = false">Tutup</button>
            </div>
        </div>
    </div>
</div>