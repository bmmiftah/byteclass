@php $kunciPush = config('pengingat.vapid.public'); @endphp

@if ($kunciPush)
    <div
        x-data="pengingatPush()"
        x-cloak
        class="nb-card mb-6"
        style="background: #fff"
        data-kunci="{{ $kunciPush }}"
        data-simpan="{{ route('saya.push.simpan') }}"
        data-hapus="{{ route('saya.push.hapus') }}"
        data-uji="{{ route('saya.push.uji') }}"
    >
        <p class="font-bold text-lg">Pengingat tugas di HP</p>
        <p class="mb-3">Dapat notifikasi saat tenggat tugas tinggal 1 hari dan 3 jam, dan saat ujian akan dibuka. Hanya di HP/browser yang lu aktifkan.</p>

        <p x-show="status === 'ios'" class="mb-2">Di iPhone/iPad, pasang dulu aplikasinya ke Layar Utama (lihat kartu "Pasang di layar utama"), lalu buka dari ikonnya dan aktifkan di sini.</p>
        <p x-show="status === 'tak-didukung'" class="mb-2">Browser ini belum mendukung notifikasi. Coba Chrome di HP Android.</p>
        <p x-show="status === 'diblokir'" class="mb-2">Notifikasi diblokir di pengaturan browser. Izinkan dulu untuk situs ini, lalu muat ulang halaman.</p>

        <div class="flex flex-wrap gap-2" x-show="status === 'mati' || status === 'hidup' || status === 'sibuk'">
            <button type="button" class="nb-btn hijau" x-show="status !== 'hidup'" :disabled="status === 'sibuk'" @click="aktifkan()">Aktifkan pengingat</button>
            <button type="button" class="nb-btn" x-show="status === 'hidup'" @click="uji()">Kirim tes</button>
            <button type="button" class="nb-btn putih" x-show="status === 'hidup'" @click="matikan()">Matikan</button>
        </div>

        <p class="mt-2 text-sm font-bold" x-show="pesan" x-text="pesan"></p>
    </div>

    <script>
        document.addEventListener('alpine:init', function () {
            Alpine.data('pengingatPush', function () {
                function kunciKeBytes(b64) {
                    var pad = '='.repeat((4 - (b64.length % 4)) % 4);
                    var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
                    var out = new Uint8Array(raw.length);
                    for (var i = 0; i < raw.length; i++) {
                        out[i] = raw.charCodeAt(i);
                    }
                    return out;
                }

                function kirim(url, isi) {
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    return fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': meta ? meta.content : '',
                        },
                        body: JSON.stringify(isi || {}),
                    }).then(function (r) {
                        return r.json().catch(function () {
                            return {};
                        }).then(function (j) {
                            return { ok: r.ok, data: j };
                        });
                    });
                }

                return {
                    status: 'mati',
                    pesan: '',

                    async init() {
                        var mandiri = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
                        var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);

                        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                            this.status = (ios && !mandiri) ? 'ios' : 'tak-didukung';
                            return;
                        }

                        if (Notification.permission === 'denied') {
                            this.status = 'diblokir';
                            return;
                        }

                        try {
                            var reg = await navigator.serviceWorker.ready;
                            var sub = await reg.pushManager.getSubscription();
                            this.status = sub ? 'hidup' : 'mati';
                        } catch (e) {
                            this.status = 'mati';
                        }
                    },

                    async aktifkan() {
                        this.pesan = '';
                        var lama = this.status;
                        this.status = 'sibuk';

                        try {
                            var izin = await Notification.requestPermission();
                            if (izin !== 'granted') {
                                this.status = izin === 'denied' ? 'diblokir' : 'mati';
                                return;
                            }

                            var reg = await navigator.serviceWorker.ready;
                            var sub = await reg.pushManager.getSubscription();
                            if (sub) {
                                await sub.unsubscribe(); // kunci server mungkin berubah: mulai bersih
                            }

                            sub = await reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: kunciKeBytes(this.$root.dataset.kunci),
                            });

                            var j = sub.toJSON();
                            var r = await kirim(this.$root.dataset.simpan, {
                                endpoint: j.endpoint,
                                keys: j.keys,
                                agen: navigator.userAgent.slice(0, 150),
                            });

                            if (!r.ok) {
                                await sub.unsubscribe();
                                this.status = 'mati';
                                this.pesan = (r.data.errors && r.data.errors.endpoint)
                                    ? r.data.errors.endpoint[0]
                                    : 'Gagal menyimpan. Coba lagi.';
                                return;
                            }

                            this.status = 'hidup';
                            this.pesan = 'Pengingat aktif di perangkat ini.';
                        } catch (e) {
                            this.status = lama === 'sibuk' ? 'mati' : lama;
                            this.pesan = 'Gagal mengaktifkan: ' + (e && e.message ? e.message : 'coba lagi');
                        }
                    },

                    async matikan() {
                        this.pesan = '';

                        try {
                            var reg = await navigator.serviceWorker.ready;
                            var sub = await reg.pushManager.getSubscription();
                            if (sub) {
                                await kirim(this.$root.dataset.hapus, { endpoint: sub.endpoint });
                                await sub.unsubscribe();
                            }

                            this.status = 'mati';
                            this.pesan = 'Pengingat dimatikan di perangkat ini.';
                        } catch (e) {
                            this.pesan = 'Gagal mematikan. Coba lagi.';
                        }
                    },

                    async uji() {
                        this.pesan = 'Mengirim...';

                        try {
                            var r = await kirim(this.$root.dataset.uji, {});
                            this.pesan = !r.ok
                                ? (r.data.pesan || 'Gagal mengirim tes.')
                                : (r.data.terkirim > 0
                                    ? 'Terkirim. Cek notifikasi di HP lu.'
                                    : 'Tidak ada perangkat yang menerima. Coba aktifkan ulang.');
                        } catch (e) {
                            this.pesan = 'Gagal mengirim tes.';
                        }
                    },
                };
            });
        });
    </script>
@endif