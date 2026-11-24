<div x-data="pasangAplikasi()" x-show="tampil" x-cloak class="nb-card mb-6" style="background: var(--biru)">
    <p class="font-bold text-lg">Pasang di layar utama HP</p>

    <template x-if="bisa">
        <div>
            <p class="mb-3">Biar bukanya cepat seperti aplikasi biasa, dan tugas yang pernah dibuka tetap bisa dilihat saat internet mati.</p>
            <button type="button" class="nb-btn" @click="pasang()">Pasang aplikasi</button>
        </div>
    </template>

    <template x-if="!bisa && ios">
        <p class="mb-1">Di iPhone/iPad: buka halaman ini di <b>Safari</b>, ketuk tombol <b>Bagikan</b> (kotak dengan panah ke atas), lalu pilih <b>Tambah ke Layar Utama</b>.</p>
    </template>

    <button type="button" class="nb-btn putih mt-3" @click="sembunyi()">Nanti saja</button>
</div>

<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('pasangAplikasi', function () {
            return {
                bisa: false,
                ios: false,
                mandiri: false,
                tutup: false,

                init: function () {
                    var self = this;
                    this.mandiri = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
                    this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !this.mandiri;

                    try {
                        var t = Number(localStorage.getItem('pasang_tutup')) || 0;
                        this.tutup = (Date.now() - t) < 14 * 86400000;
                    } catch (e) {}

                    this.bisa = !!window.__pasang;

                    window.addEventListener('bisa-pasang', function () {
                        self.bisa = true;
                    });

                    window.addEventListener('sudah-pasang', function () {
                        self.bisa = false;
                        self.mandiri = true;
                    });
                },

                get tampil() {
                    return !this.mandiri && !this.tutup && (this.bisa || this.ios);
                },

                pasang: function () {
                    var self = this;
                    var e = window.__pasang;
                    if (!e) return;

                    e.prompt();
                    e.userChoice.then(function () {
                        window.__pasang = null;
                        self.bisa = false;
                    });
                },

                sembunyi: function () {
                    this.tutup = true;
                    try {
                        localStorage.setItem('pasang_tutup', String(Date.now()));
                    } catch (e) {}
                },
            };
        });
    });
</script>