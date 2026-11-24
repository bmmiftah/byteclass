<?php

namespace Tests\Feature;

require_once __DIR__ . '/../stubs.php';

use App\Models\{Agenda, AkunSiswa, Kelas, LanggananPush, PengingatTerkirim, Siswa, Tugas, TugasPengumpulan, Ujian, User};
use App\Services\{IcsPembangun, PengingatSiswa, PengirimWebPush};
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as Permintaan;
use GuzzleHttp\Psr7\Response as Balasan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pengirim palsu: mencatat apa yang akan dikirim, tanpa menyentuh internet. */
class PengirimPalsu extends PengirimWebPush
{
    public array $kiriman = [];

    public array $hasil = []; // antrean hasil: 'ok' | 'kadaluarsa' | 'gagal'

    public bool $siapnya = true;

    public function siap(): bool
    {
        return $this->siapnya;
    }

    public function kirim(LanggananPush $langganan, array $isi, int $ttl = 3600): string
    {
        $this->kiriman[] = ['id' => $langganan->id, 'isi' => $isi, 'ttl' => $ttl];

        return array_shift($this->hasil) ?? 'ok';
    }
}

class F8dTest extends TestCase
{
    use RefreshDatabase;

    private const FCM = 'https://fcm.googleapis.com/fcm/send/abc123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-11-21 10:00:00'); // Sabtu 10.00 WIB
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function guru(string $nama = 'Ani'): User
    {
        $u = User::factory()->create(['name' => $nama, 'email' => strtolower($nama) . '@x.id']);
        $u->forceFill(['peran' => 'guru'])->save();

        return $u->refresh();
    }

    private function kelas(User $g, string $nama = '7A', array $siswa = ['Budi Santoso', 'Sari Dewi']): array
    {
        $k = Kelas::create(['guru_id' => $g->id, 'nama' => $nama]);
        $out = [];

        foreach ($siswa as $n) {
            $s = Siswa::create(['kelas_id' => $k->id, 'nama' => $n]);
            $out[] = [$s, AkunSiswa::create(['siswa_id' => $s->id, 'kode' => AkunSiswa::kodeBaru()])];
        }

        return [$k, $out];
    }

    private function masuk(AkunSiswa $a): void
    {
        $this->flushSession();
        $this->post(route('saya.proses'), ['kode' => $a->kode])->assertRedirect();
    }

    private function tugas(Kelas $k, string $judul, string $tenggat, array $o = []): Tugas
    {
        return Tugas::create($o + [
            'kelas_id'     => $k->id,
            'judul'        => $judul,
            'jenis'        => 'teks',
            'tenggat'      => $tenggat,
            'status'       => 'terbit',
            'telat'        => 'tutup',
            'boleh_ulang'  => true,
            'maks_berkas'  => 3,
            'ukuran_mb'    => 10,
            'jenis_berkas' => 'umum',
        ]);
    }

    private function ujian(Kelas $k, string $judul, string $buka, array $o = []): Ujian
    {
        return Ujian::create($o + [
            'guru_id'    => $k->guru_id,
            'kelas_id'   => $k->id,
            'judul'      => $judul,
            'durasi'     => 30,
            'token'      => AkunSiswa::acak(6),
            'status'     => 'buka',
            'buka_pada'  => $buka,
            'tutup_pada' => Carbon::parse($buka)->addHour(),
        ]);
    }

    private function push(AkunSiswa $a, string $endpoint = self::FCM): LanggananPush
    {
        return LanggananPush::create([
            'akun_siswa_id' => $a->id,
            'endpoint_hash' => LanggananPush::hash($endpoint),
            'endpoint'      => $endpoint,
            'p256dh'        => 'BPk',
            'auth'          => 'abc',
        ]);
    }

    private function palsu(): PengirimPalsu
    {
        $p = new PengirimPalsu();
        $this->app->instance(PengirimWebPush::class, $p);

        return $p;
    }

    /* ---------- PWA ---------- */

    public function test_manifest_lengkap_dan_ikon_ada(): void
    {
        $r = $this->get('/manifest.webmanifest')->assertOk();
        $this->assertStringContainsString('application/manifest+json', $r->headers->get('Content-Type'));

        $m = json_decode($r->getContent(), true);
        $this->assertSame('/saya', $m['start_url']);
        $this->assertSame('/', $m['scope']); // CBT dan materi tetap di dalam aplikasi
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('id', $m['lang']);

        $sizes = [];
        foreach ($m['icons'] as $ic) {
            $file = public_path(ltrim($ic['src'], '/'));
            $this->assertFileExists($file);
            [$w, $h] = getimagesize($file);
            $this->assertSame($ic['sizes'], "{$w}x{$h}");
            $sizes[] = $ic['sizes'] . ':' . $ic['purpose'];
        }

        $this->assertContains('192x192:any', $sizes);
        $this->assertContains('512x512:any', $sizes);
        $this->assertContains('512x512:maskable', $sizes);

        [$w] = getimagesize(public_path('pwa/apple-touch-icon.png'));
        $this->assertSame(180, $w);
        $this->assertFileExists(public_path('pwa/badge-96.png'));
    }

    public function test_service_worker_dan_halaman_offline_tanpa_login(): void
    {
        $r = $this->get('/saya/sw.js')->assertOk();
        $this->assertStringContainsString('javascript', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $r->headers->get('Cache-Control'));
        $this->assertSame('/', $r->headers->get('Service-Worker-Allowed')); // cakupan "/" supaya alamat /saya (beranda) ikut

        $js = $r->getContent();
        $this->assertStringContainsString("'/saya/offline'", $js);
        $this->assertStringContainsString("addEventListener('push'", $js);
        $this->assertStringContainsString("addEventListener('notificationclick'", $js);
        $this->assertStringContainsString("'bersihkan'", $js);
        $this->assertStringContainsString('berkas', $js); // unduhan file tidak disimpan
        $this->assertStringNotContainsString('{{', $js); // tidak ada sisa sintaks Blade

        $this->get('/saya/offline')->assertOk()->assertSee('Lagi offline')->assertSee('Coba lagi');
    }

    public function test_halaman_siswa_memuat_manifest_dan_membersihkan_simpanan_di_halaman_masuk(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);

        $masuk = $this->get(route('saya.masuk'))->assertOk();
        $masuk->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('<meta name="theme-color" content="#FF8C42">', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee("register('/saya/sw.js', { scope: '/' })", false)
            ->assertSee('window.bersihkanCacheSaya();', false); // dipanggil otomatis di halaman masuk

        $this->masuk($ak[0][1]);

        $ber = $this->get(route('saya.beranda'))->assertOk();
        $ber->assertSee('<link rel="manifest"', false)
            ->assertDontSee('window.bersihkanCacheSaya();', false) // tidak otomatis saat sudah masuk
            ->assertSee('onsubmit="window.bersihkanCacheSaya', false); // tapi dipanggil saat menekan Keluar
    }

    /* ---------- ICS: pembangun ---------- */

    public function test_ics_escape_dan_lipat_baris(): void
    {
        $this->assertSame('a\\, b\\; c\\\\d\\ne', IcsPembangun::escape("a, b; c\\d\ne"));

        $panjang = str_repeat('Tugas Fotosintesis é ', 20);
        $lipat = IcsPembangun::lipat('SUMMARY:' . $panjang);
        $bagian = explode("\r\n", $lipat);

        $this->assertGreaterThan(1, count($bagian));

        foreach ($bagian as $i => $b) {
            $this->assertLessThanOrEqual(75, strlen($b));
            $this->assertTrue(mb_check_encoding($b, 'UTF-8'), 'karakter UTF-8 terpotong di tengah');
            if ($i > 0) {
                $this->assertStringStartsWith(' ', $b);
            }
        }

        // Dilipat lalu disambung lagi = sama dengan aslinya
        $this->assertSame('SUMMARY:' . $panjang, str_replace("\r\n ", '', $lipat));
        $this->assertSame('pendek', IcsPembangun::lipat('pendek'));
    }

    /* ---------- ICS: langganan ---------- */

    public function test_kelola_langganan_kalender(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $akun = $ak[0][1];

        // Wajib masuk
        $this->post(route('saya.kalender.langganan'))->assertRedirect(route('saya.masuk'));

        $this->masuk($akun);
        $this->get(route('saya.kalender'))->assertOk()->assertSee('Kalender di HP')->assertSee('Aktifkan langganan kalender');

        $this->post(route('saya.kalender.langganan'))->assertRedirect();
        $token = $akun->refresh()->kal_token;
        $this->assertSame(40, strlen($token));

        $this->get(route('saya.kalender'))->assertOk()
            ->assertSee(route('kalender.ics', $token))
            ->assertSee('webcal://', false)
            ->assertSee('Buat tautan baru');

        // Aktifkan lagi tidak mengganti token
        $this->post(route('saya.kalender.langganan'));
        $this->assertSame($token, $akun->refresh()->kal_token);
        $this->get(route('kalender.ics', $token))->assertOk();

        // Buat ulang: tautan lama mati, yang baru hidup
        $this->post(route('saya.kalender.langganan.ulang'))->assertRedirect();
        $baru = $akun->refresh()->kal_token;
        $this->assertNotSame($token, $baru);
        $this->get(route('kalender.ics', $token))->assertNotFound();
        $this->get(route('kalender.ics', $baru))->assertOk();

        // Matikan
        $this->delete(route('saya.kalender.langganan.mati'))->assertRedirect();
        $this->assertNull($akun->refresh()->kal_token);
        $this->get(route('kalender.ics', $baru))->assertNotFound();
    }

    public function test_feed_ics_menolak_token_salah_dan_siswa_nonaktif(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $akun = $ak[0][1];
        $token = $akun->buatTokenKalender();

        $this->get('/kal/pendek.ics')->assertNotFound(); // bentuk token salah
        $this->get('/kal/' . str_repeat('a', 40) . '.ics')->assertNotFound(); // bentuk benar, tidak ada
        $this->get('/kal/' . $token . '.ics')->assertOk();

        $ak[0][0]->update(['aktif' => false]);
        $this->get('/kal/' . $token . '.ics')->assertNotFound();
    }

    public function test_isi_feed_ics_benar(): void
    {
        $g = $this->guru();
        [$k, $ak] = $this->kelas($g);
        [$kLain] = $this->kelas($g, '8B', ['Joko']);
        $akun = $ak[0][1];
        $token = $akun->buatTokenKalender();

        $a = $this->tugas($k, 'Tugas A', '2026-11-24 23:59:00'); // 16.59 UTC
        $sudah = $this->tugas($k, 'Tugas Selesai', '2026-11-25 08:00:00');
        TugasPengumpulan::create(['tugas_id' => $sudah->id, 'siswa_id' => $ak[0][0]->id, 'dikirim_pada' => now()]);

        $this->tugas($k, 'Tugas Draf', '2026-11-26 08:00:00', ['status' => 'draf']);
        $this->tugas($k, 'Tugas Jadwal Nanti', '2026-11-26 08:00:00', ['terbit_pada' => now()->addDays(2)]);
        $this->tugas($k, 'Tugas Kuno', '2026-09-01 08:00:00'); // lebih lama dari 30 hari
        $this->tugas($kLain, 'Tugas Kelas Lain', '2026-11-26 08:00:00');

        $this->ujian($k, 'Kuis 1', '2026-11-26 08:00:00');

        Agenda::create([
            'kelas_id'   => $k->id,
            'judul'      => 'Libur, Semester; Ganjil',
            'jenis'      => 'libur',
            'mulai'      => '2026-11-27 00:00:00',
            'selesai'    => '2026-11-29 00:00:00',
            'seharian'   => true,
            'keterangan' => "Baris satu\nBaris dua",
        ]);

        Agenda::create([
            'kelas_id' => $k->id,
            'judul'    => 'Rapat Kelas',
            'jenis'    => 'kegiatan',
            'mulai'    => '2026-12-01 13:00:00',
            'selesai'  => '2026-12-01 14:30:00',
            'seharian' => false,
        ]);

        $r = $this->get('/kal/' . $token . '.ics')->assertOk();
        $this->assertStringContainsString('text/calendar', $r->headers->get('Content-Type'));

        $ics = $r->getContent();
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertSame(substr_count($ics, 'BEGIN:VEVENT'), substr_count($ics, 'END:VEVENT'));
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $ics), 'semua baris harus diakhiri CRLF');

        foreach (explode("\r\n", rtrim($ics, "\r\n")) as $baris) {
            $this->assertLessThanOrEqual(75, strlen($baris));
        }

        $tanpaLipat = str_replace("\r\n ", '', $ics);

        // Yang harus ada
        $this->assertStringContainsString('X-WR-CALNAME:Kelas 7A', $tanpaLipat);
        $this->assertStringContainsString('SUMMARY:Tenggat: Tugas A', $tanpaLipat);
        $this->assertStringContainsString('DTSTART:20261124T165900Z', $tanpaLipat); // 23.59 WIB = 16.59 UTC
        $this->assertStringContainsString('SUMMARY:Sudah dikumpulkan: Tugas Selesai', $tanpaLipat);
        $this->assertStringContainsString('SUMMARY:Ujian: Kuis 1', $tanpaLipat);
        $this->assertStringContainsString('DTSTART:20261126T010000Z', $tanpaLipat); // 08.00 WIB
        $this->assertStringContainsString('DTEND:20261126T020000Z', $tanpaLipat); // sampai ditutup 09.00 WIB
        $this->assertStringContainsString('SUMMARY:Libur\\, Semester\\; Ganjil', $tanpaLipat);
        $this->assertStringContainsString('DESCRIPTION:Baris satu\\nBaris dua', $tanpaLipat);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261127', $tanpaLipat);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261130', $tanpaLipat); // 27-29 Nov, DTEND eksklusif
        $this->assertStringContainsString('SUMMARY:Rapat Kelas', $tanpaLipat);
        $this->assertStringContainsString('DTSTART:20261201T060000Z', $tanpaLipat); // 13.00 WIB
        $this->assertStringContainsString('DTEND:20261201T073000Z', $tanpaLipat);

        // Yang tidak boleh ada
        foreach (['Tugas Draf', 'Tugas Jadwal Nanti', 'Tugas Kuno', 'Tugas Kelas Lain'] as $tak) {
            $this->assertStringNotContainsString($tak, $tanpaLipat);
        }

        // Alarm: tugas belum dikumpulkan punya 2 alarm, yang sudah dikumpulkan tidak punya
        $acara = array_values(array_filter(explode('BEGIN:VEVENT', $tanpaLipat)));
        $blokA = collect($acara)->first(fn ($b) => str_contains($b, 'Tenggat: Tugas A'));
        $blokSelesai = collect($acara)->first(fn ($b) => str_contains($b, 'Sudah dikumpulkan: Tugas Selesai'));

        $this->assertSame(2, substr_count($blokA, 'BEGIN:VALARM'));
        $this->assertStringContainsString('TRIGGER:-PT1440M', $blokA);
        $this->assertStringContainsString('TRIGGER:-PT120M', $blokA);
        $this->assertSame(0, substr_count($blokSelesai, 'VALARM'));

        // UID unik dan stabil
        preg_match_all('/^UID:(.+)$/m', str_replace("\r", '', $tanpaLipat), $m);
        $this->assertCount(count(array_unique($m[1])), $m[1]);
        $this->assertSame($ics, $this->get('/kal/' . $token . '.ics')->getContent());
    }

    /* ---------- Pengingat ---------- */

    public function test_aturan_pengingat_siswa(): void
    {
        $g = $this->guru();
        [$k, $ak] = $this->kelas($g);
        $siswa = $ak[0][0];

        $dekat = $this->tugas($k, 'Dekat', '2026-11-21 12:00:00'); // 2 jam lagi -> 3j
        $hariIni = $this->tugas($k, 'Hari Ini', '2026-11-21 20:00:00'); // 10 jam lagi -> 24j
        $this->tugas($k, 'Jauh', '2026-11-23 08:00:00'); // > 24 jam
        $this->tugas($k, 'Lewat', '2026-11-21 09:00:00'); // sudah lewat
        $this->tugas($k, 'Draf', '2026-11-21 11:00:00', ['status' => 'draf']);
        $this->tugas($k, 'Jadwal', '2026-11-21 11:00:00', ['terbit_pada' => now()->addHour()]);

        $selesai = $this->tugas($k, 'Selesai', '2026-11-21 11:30:00');
        TugasPengumpulan::create(['tugas_id' => $selesai->id, 'siswa_id' => $siswa->id, 'dikirim_pada' => now()]);

        $this->ujian($k, 'Ujian Dekat', '2026-11-21 10:30:00');
        $this->ujian($k, 'Ujian Jauh', '2026-11-21 12:00:00');
        $this->ujian($k, 'Ujian Draf', '2026-11-21 10:20:00', ['status' => 'draf']);

        $daftar = PengingatSiswa::untuk($siswa);
        $kunci = $daftar->pluck('kunci')->all();

        $this->assertEqualsCanonicalizing([
            "tugas:{$dekat->id}:3j",
            "tugas:{$hariIni->id}:24j",
            'ujian:' . Ujian::where('judul', 'Ujian Dekat')->value('id') . ':mulai',
        ], $kunci);

        $this->assertTrue($daftar->firstWhere('kunci', "tugas:{$dekat->id}:3j")['mendesak']);
        $this->assertFalse($daftar->firstWhere('kunci', "tugas:{$hariIni->id}:24j")['mendesak']);

        // Terurut menurut waktu: ujian 10.30, tugas 12.00, tugas 20.00
        $this->assertSame('ujian', $daftar[0]['jenis']);
        $this->assertSame("tugas:{$dekat->id}:3j", $daftar[1]['kunci']);
        $this->assertStringStartsWith('/saya/tugas/', $daftar[1]['url']); // tautan relatif (untuk notifikasi)

        // 1 jam kemudian: yang 24j sudah jadi jendela 3j? (20.00 - 11.00 = 9 jam -> masih 24j)
        Carbon::setTestNow('2026-11-21 17:30:00'); // 2,5 jam sebelum 20.00
        $this->assertContains("tugas:{$hariIni->id}:3j", PengingatSiswa::untuk($siswa)->pluck('kunci')->all());
    }

    public function test_kartu_segera_dan_badge_di_beranda(): void
    {
        $g = $this->guru();
        [$k, $ak] = $this->kelas($g);
        $this->masuk($ak[0][1]);

        $this->get(route('saya.beranda'))->assertOk()->assertDontSee('Segera!')->assertSee('var n = 0;', false);

        $this->tugas($k, 'Laporan Praktikum', '2026-11-21 12:00:00');
        $this->get(route('saya.beranda'))->assertOk()
            ->assertSee('Segera!')
            ->assertSee('Laporan Praktikum')
            ->assertSee('Tugas segera berakhir')
            ->assertSee('var n = 1;', false);
    }

    /* ---------- Push: pendaftaran ---------- */

    private function datasub(array $o = []): array
    {
        return array_replace_recursive([
            'endpoint' => self::FCM,
            'keys'     => ['p256dh' => 'BPkAbc-_123=', 'auth' => 'authKey-_9'],
        ], $o);
    }

    public function test_push_wajib_masuk(): void
    {
        $this->postJson(route('saya.push.simpan'), $this->datasub())->assertStatus(401);
        $this->postJson(route('saya.push.hapus'), ['endpoint' => self::FCM])->assertStatus(401);
        $this->postJson(route('saya.push.uji'))->assertStatus(401);
        $this->assertSame(0, LanggananPush::count());
    }

    public function test_simpan_hapus_push_dan_penjaga_host(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $this->masuk($ak[0][1]);

        $this->postJson(route('saya.push.simpan'), $this->datasub(['agen' => 'Chrome Android']))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, LanggananPush::count());
        $this->assertSame($ak[0][1]->id, LanggananPush::first()->akun_siswa_id);

        // Endpoint sama didaftarkan lagi: tidak dobel
        $this->postJson(route('saya.push.simpan'), $this->datasub())->assertOk();
        $this->assertSame(1, LanggananPush::count());

        // Layanan yang diizinkan
        foreach ([
            'https://updates.push.services.mozilla.com/wpush/v2/x',
            'https://web.push.apple.com/abc',
            'https://wns2-par02p.notify.windows.com/w/x',
        ] as $ok) {
            $this->postJson(route('saya.push.simpan'), $this->datasub(['endpoint' => $ok]))->assertOk();
        }
        $this->assertSame(4, LanggananPush::count());

        // Ditolak: http biasa, alamat dalam, penyamaran nama, port aneh, userinfo, bukan URL, kunci aneh
        $tolak = [
            'http://fcm.googleapis.com/x',
            'https://localhost/x',
            'https://127.0.0.1/x',
            'https://169.254.169.254/latest',
            'https://fcm.googleapis.com.evil.com/x',
            'https://evilfcm.googleapis.com.evil.io/x',
            'https://evil.com/fcm.googleapis.com',
            'https://fcm.googleapis.com:8443/x',
            'https://user:pw@fcm.googleapis.com/x',
            'bukan-url',
        ];
        foreach ($tolak as $t) {
            $this->postJson(route('saya.push.simpan'), $this->datasub(['endpoint' => $t]))->assertStatus(422);
        }

        $this->postJson(route('saya.push.simpan'), $this->datasub(['keys' => ['auth' => 'a b<script>']]))->assertStatus(422);
        $this->postJson(route('saya.push.simpan'), ['endpoint' => self::FCM])->assertStatus(422);
        $this->assertSame(4, LanggananPush::count());

        // Hapus milik sendiri
        $this->postJson(route('saya.push.hapus'), ['endpoint' => self::FCM])->assertOk();
        $this->assertSame(3, LanggananPush::count());
    }

    public function test_push_tidak_bisa_dihapus_akun_lain_dan_hp_bergantian_pindah_akun(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $this->push($ak[0][1]);

        // Siswa lain mencoba menghapus endpoint milik siswa pertama: tidak terhapus
        $this->masuk($ak[1][1]);
        $this->postJson(route('saya.push.hapus'), ['endpoint' => self::FCM])->assertOk();
        $this->assertSame(1, LanggananPush::count());

        // HP yang sama dipakai siswa kedua mengaktifkan: pindah ke siswa kedua, tidak dobel
        $this->postJson(route('saya.push.simpan'), $this->datasub())->assertOk();
        $this->assertSame(1, LanggananPush::count());
        $this->assertSame($ak[1][1]->id, LanggananPush::first()->akun_siswa_id);
    }

    public function test_maksimal_lima_perangkat_per_siswa(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $this->masuk($ak[0][1]);

        foreach (range(1, 7) as $i) {
            Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00')->addMinutes($i));
            $this->postJson(route('saya.push.simpan'), $this->datasub(['endpoint' => "https://fcm.googleapis.com/fcm/send/dev{$i}"]))->assertOk();
        }

        $this->assertSame(5, LanggananPush::where('akun_siswa_id', $ak[0][1]->id)->count());
        $this->assertFalse(LanggananPush::where('endpoint', 'like', '%dev1')->exists()); // yang terlama dibuang
        $this->assertTrue(LanggananPush::where('endpoint', 'like', '%dev7')->exists());
    }

    public function test_kirim_tes_notifikasi(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $this->masuk($ak[0][1]);

        $pengirim = $this->palsu();
        $pengirim->siapnya = false;
        $this->postJson(route('saya.push.uji'))->assertStatus(503);
        $this->assertCount(0, $pengirim->kiriman);

        $pengirim->siapnya = true;
        $a = $this->push($ak[0][1], self::FCM);
        $b = $this->push($ak[0][1], 'https://fcm.googleapis.com/fcm/send/kedua');
        $this->push($ak[1][1], 'https://fcm.googleapis.com/fcm/send/orang-lain');

        $pengirim->hasil = ['ok', 'kadaluarsa'];
        $this->postJson(route('saya.push.uji'))->assertOk()->assertJson(['terkirim' => 1]);

        $this->assertCount(2, $pengirim->kiriman); // hanya perangkat milik sendiri
        $this->assertSame('Notifikasi aktif', $pengirim->kiriman[0]['isi']['judul']);
        $this->assertFalse(LanggananPush::whereKey($b->id)->exists()); // yang kedaluwarsa dibuang
        $this->assertTrue(LanggananPush::whereKey($a->id)->exists());
    }

    /* ---------- Pengirim push asli (klien HTTP palsu) ---------- */

    public function test_pengirim_webpush_asli_membentuk_permintaan_dan_membaca_balasan(): void
    {
        // Pasangan kunci "perangkat" asli, seperti yang dihasilkan browser
        $kunci = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($kunci);
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $pub = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $vapid = \Minishlink\WebPush\VAPID::createVapidKeys();
        config(['pengingat.vapid' => [
            'public'  => $vapid['publicKey'],
            'private' => $vapid['privateKey'],
            'subject' => 'mailto:uji@example.com',
        ]]);

        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $l = $this->push($ak[0][1]);
        $l->update(['p256dh' => $b64($pub), 'auth' => $b64(random_bytes(16))]);

        $riwayat = [];
        $buat = function (array $balasan) use (&$riwayat) {
            $riwayat = [];
            $tumpuk = HandlerStack::create(new MockHandler($balasan));
            $tumpuk->push(Middleware::history($riwayat));

            return new PengirimWebPush(new Client(['handler' => $tumpuk, 'http_errors' => false]));
        };

        // Sukses: 201 (dan 202)
        $p = $buat([new Balasan(201)]);
        $this->assertTrue($p->siap());
        $this->assertSame('ok', $p->kirim($l, ['judul' => 'Halo', 'isi' => 'Tugas é', 'url' => '/saya'], 1800));

        $req = $riwayat[0]['request'];
        $this->assertSame('POST', $req->getMethod());
        $this->assertSame(self::FCM, (string) $req->getUri());
        $this->assertSame('aes128gcm', $req->getHeaderLine('Content-Encoding')); // wajib untuk Safari/iPhone
        $this->assertStringStartsWith('vapid t=', $req->getHeaderLine('Authorization'));
        $this->assertStringContainsString('k=' . $vapid['publicKey'], $req->getHeaderLine('Authorization'));
        $this->assertSame('1800', $req->getHeaderLine('TTL'));
        $this->assertGreaterThan(100, strlen((string) $req->getBody()));
        $this->assertStringNotContainsString('Halo', (string) $req->getBody()); // isi terenkripsi, bukan teks biasa

        $this->assertSame('ok', $buat([new Balasan(202)])->kirim($l, ['judul' => 'a', 'isi' => 'b', 'url' => '/saya']));

        // Perangkat sudah tidak berlaku
        $this->assertSame('kadaluarsa', $buat([new Balasan(410)])->kirim($l, ['judul' => 'a', 'isi' => 'b', 'url' => '/saya']));
        $this->assertSame('kadaluarsa', $buat([new Balasan(404)])->kirim($l, ['judul' => 'a', 'isi' => 'b', 'url' => '/saya']));

        // Gangguan sementara: dicoba lagi nanti
        $this->assertSame('gagal', $buat([new Balasan(500)])->kirim($l, ['judul' => 'a', 'isi' => 'b', 'url' => '/saya']));
        $this->assertSame('gagal', $buat([new Balasan(429)])->kirim($l, ['judul' => 'a', 'isi' => 'b', 'url' => '/saya']));
        $this->assertSame('gagal', $buat([new ConnectException('putus', new Permintaan('POST', self::FCM))])->kirim($l, ['judul' => 'a', 'isi' => 'b', 'url' => '/saya']));

        // Kunci VAPID kosong = belum siap
        config(['pengingat.vapid.private' => null]);
        $this->assertFalse((new PengirimWebPush())->siap());
    }

    /* ---------- Push: pengiriman terjadwal ---------- */

    public function test_perintah_kirim_pengingat_tanpa_dobel(): void
    {
        $g = $this->guru();
        [$k, $ak] = $this->kelas($g);
        $langganan = $this->push($ak[0][1]);
        $t = $this->tugas($k, 'Laporan', '2026-11-21 20:00:00'); // 10 jam lagi

        $pengirim = $this->palsu();
        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertCount(1, $pengirim->kiriman);

        $isi = $pengirim->kiriman[0]['isi'];
        $this->assertSame('Tugas segera berakhir', $isi['judul']);
        $this->assertStringContainsString('Laporan', $isi['isi']);
        $this->assertSame("/saya/tugas/{$t->id}", $isi['url']);
        $this->assertSame("tugas:{$t->id}:24j", $isi['tag']);
        $this->assertSame(1, $isi['badge']);
        $this->assertSame(7200, $pengirim->kiriman[0]['ttl']);
        $this->assertNotNull($langganan->refresh()->terakhir_kirim);

        // Diulang: tidak dikirim lagi
        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertCount(1, $pengirim->kiriman);

        // 3 jam sebelum tenggat: jendela baru ("3j"), dikirim sekali
        Carbon::setTestNow('2026-11-21 17:30:00');
        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertCount(2, $pengirim->kiriman);
        $this->assertSame("tugas:{$t->id}:3j", $pengirim->kiriman[1]['isi']['tag']);
        $this->assertSame(1800, $pengirim->kiriman[1]['ttl']);

        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertCount(2, $pengirim->kiriman);

        // Setelah tenggat lewat: tidak ada lagi
        Carbon::setTestNow('2026-11-21 20:05:00');
        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertCount(2, $pengirim->kiriman);
    }

    public function test_perintah_kirim_hanya_untuk_yang_berhak(): void
    {
        $g = $this->guru();
        [$k, $ak] = $this->kelas($g, '7A', ['Budi', 'Sari', 'Tono', 'Dewi']);
        [$kLain] = $this->kelas($g, '8B', ['Joko']);

        $pengirim = $this->palsu();
        $this->tugas($k, 'Laporan', '2026-11-21 12:00:00');
        $this->tugas($kLain, 'Tugas 8B', '2026-11-21 12:00:00');

        $this->push($ak[0][1], 'https://fcm.googleapis.com/fcm/send/budi'); // berhak
        // ak[1] (Sari) tidak mengaktifkan push
        $this->push($ak[2][1], 'https://fcm.googleapis.com/fcm/send/tono'); // sudah mengumpulkan
        TugasPengumpulan::create([
            'tugas_id'     => Tugas::where('judul', 'Laporan')->value('id'),
            'siswa_id'     => $ak[2][0]->id,
            'dikirim_pada' => now(),
        ]);

        $this->push($ak[3][1], 'https://fcm.googleapis.com/fcm/send/dewi'); // nonaktif
        $ak[3][0]->update(['aktif' => false]);

        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertCount(1, $pengirim->kiriman);
        $this->assertSame(LanggananPush::where('endpoint', 'like', '%budi')->value('id'), $pengirim->kiriman[0]['id']);
        $this->assertStringContainsString('Laporan', $pengirim->kiriman[0]['isi']['isi']); // bukan tugas kelas lain
    }

    public function test_perintah_kirim_menangani_gagal_dan_kedaluwarsa(): void
    {
        $g = $this->guru();
        [$k, $ak] = $this->kelas($g);
        $p1 = $this->push($ak[0][1], 'https://fcm.googleapis.com/fcm/send/satu');
        $p2 = $this->push($ak[0][1], 'https://fcm.googleapis.com/fcm/send/dua');
        $this->tugas($k, 'Laporan', '2026-11-21 12:00:00');

        $pengirim = $this->palsu();

        // Semua gagal sementara: tidak dicatat, dicoba lagi putaran berikutnya
        $pengirim->hasil = ['gagal', 'gagal'];
        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertSame(0, PengingatTerkirim::count());
        $this->assertSame(2, LanggananPush::count());

        // Satu kedaluwarsa (dibuang), satu berhasil (dicatat)
        $pengirim->hasil = ['kadaluarsa', 'ok'];
        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertSame(1, PengingatTerkirim::count());
        $this->assertFalse(LanggananPush::whereKey($p1->id)->exists());
        $this->assertTrue(LanggananPush::whereKey($p2->id)->exists());
        $this->assertCount(4, $pengirim->kiriman);
    }

    public function test_perintah_kirim_mode_kering_dan_tanpa_kunci_vapid(): void
    {
        $g = $this->guru();
        [$k, $ak] = $this->kelas($g);
        $this->push($ak[0][1]);
        $this->tugas($k, 'Laporan', '2026-11-21 12:00:00');

        $pengirim = $this->palsu();
        $pengirim->siapnya = false;
        $this->artisan('pengingat:kirim')->expectsOutputToContain('Kunci VAPID belum diisi')->assertSuccessful();
        $this->assertCount(0, $pengirim->kiriman);

        $pengirim->siapnya = true;
        $this->artisan('pengingat:kirim', ['--kering' => true])->expectsOutputToContain('Laporan')->assertSuccessful();
        $this->assertCount(0, $pengirim->kiriman);
        $this->assertSame(0, PengingatTerkirim::count());
    }

    public function test_catatan_lama_dibersihkan_dan_terjadwal(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $this->push($ak[0][1]);
        $this->palsu();

        PengingatTerkirim::create([
            'akun_siswa_id' => $ak[0][1]->id,
            'kunci'         => 'tugas:1:24j',
            'dikirim_pada'  => now()->subDays(15),
        ]);
        PengingatTerkirim::create([
            'akun_siswa_id' => $ak[0][1]->id,
            'kunci'         => 'tugas:2:24j',
            'dikirim_pada'  => now()->subDays(2),
        ]);

        $this->artisan('pengingat:kirim')->assertSuccessful();
        $this->assertSame(['tugas:2:24j'], PengingatTerkirim::pluck('kunci')->all());

        $perintah = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($e) => $e->command)
            ->implode("\n");
        $this->assertStringContainsString('pengingat:kirim', $perintah);
    }

    public function test_perintah_buat_kunci_vapid(): void
    {
        $this->artisan('pengingat:kunci')->assertSuccessful();

        $out = new \Symfony\Component\Console\Output\BufferedOutput();
        \Illuminate\Support\Facades\Artisan::call('pengingat:kunci', [], $out);
        $teks = $out->fetch();

        preg_match('/VAPID_PUBLIC_KEY=([A-Za-z0-9_-]+)/', $teks, $pub);
        preg_match('/VAPID_PRIVATE_KEY=([A-Za-z0-9_-]+)/', $teks, $pri);

        $this->assertSame(87, strlen($pub[1] ?? '')); // titik kurva P-256, 65 byte, base64url
        $this->assertSame(43, strlen($pri[1] ?? '')); // 32 byte
    }

    public function test_kartu_pengingat_push_hanya_tampil_bila_kunci_ada(): void
    {
        $g = $this->guru();
        [, $ak] = $this->kelas($g);
        $this->masuk($ak[0][1]);

        config(['pengingat.vapid.public' => null]);
        $this->get(route('saya.beranda'))->assertOk()->assertDontSee('Pengingat tugas di HP');

        config(['pengingat.vapid.public' => 'BKunciPublikContoh']);
        $this->get(route('saya.beranda'))->assertOk()
            ->assertSee('Pengingat tugas di HP')
            ->assertSee('data-kunci="BKunciPublikContoh"', false)
            ->assertSee(route('saya.push.simpan'), false);
    }
}