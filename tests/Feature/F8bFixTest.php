<?php

namespace Tests\Feature;

require_once __DIR__ . '/../stubs.php';

use App\Models\{AkunSiswa, Kelas, Siswa, Tugas, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Perbaikan 8B: zona waktu WIB + jadwal tayang otomatis terbit. */
class F8bFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');

        // 22 Nov 2026, jam 12.04 WIB (= 05.04 UTC), persis kasus yang dilaporkan
        Carbon::setTestNow(Carbon::parse('2026-11-22 12:04:49', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function siap(): array
    {
        $g = User::factory()->create([
            'email'    => 'ani@x.id',
            'password' => Hash::make('rahasia123'),
        ]);
        $g->forceFill(['peran' => 'guru'])->save();

        $k = Kelas::create(['guru_id' => $g->id, 'nama' => '7A']);
        $s = Siswa::create(['kelas_id' => $k->id, 'nama' => 'Budi Santoso']);
        $a = AkunSiswa::create(['siswa_id' => $s->id, 'kode' => AkunSiswa::kodeBaru()]);

        return [$g->refresh(), $k, $a];
    }

    private function isi(array $o = []): array
    {
        return $o + [
            'judul'        => 'Tugas Jadwal',
            'jenis'        => 'file',
            'tenggat'      => '2026-11-23T10:07',
            'telat'        => 'tutup',
            'maks_berkas'  => 3,
            'ukuran_mb'    => 5,
            'jenis_berkas' => 'umum',
        ];
    }

    private function masukSiswa(AkunSiswa $a): void
    {
        $this->flushSession();
        $this->post(route('saya.proses'), ['kode' => $a->kode])->assertRedirect();
    }

    public function test_zona_waktu_aplikasi_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertSame('12:04', now()->format('H:i'));
        $this->assertSame('+07:00', now()->format('P'));
    }

    public function test_jadwal_diisi_otomatis_terbit_dan_tampil_saat_waktunya(): void
    {
        [$g, $k, $a] = $this->siap();

        // Jadwal 14.00 WIB, sekarang 12.04 WIB -> terjadwal, siswa belum lihat
        $this->actingAs($g)
            ->post(route('tugas.store', $k), $this->isi(['terbit_pada' => '2026-11-22T14:00']))
            ->assertRedirect();

        $t = Tugas::first();
        $this->assertSame('terbit', $t->status);
        $this->assertTrue($t->terjadwal);
        $this->assertSame('Terjadwal', $t->labelStatus());

        $this->get(route('tugas.index', $k))->assertSee('Terjadwal');
        $this->get(route('tugas.hasil', $t))->assertSee('Terjadwal')->assertSee('Tayang di siswa mulai');

        $this->masukSiswa($a);
        $this->get(route('saya.beranda'))->assertOk()->assertDontSee('Tugas Jadwal');

        // Jam 13.59 belum, 14.00 sudah
        Carbon::setTestNow(Carbon::parse('2026-11-22 13:59:00', 'Asia/Jakarta'));
        $this->assertSame(0, Tugas::terlihat()->count());

        Carbon::setTestNow(Carbon::parse('2026-11-22 14:00:00', 'Asia/Jakarta'));
        $this->assertSame(1, Tugas::terlihat()->count());

        $t->refresh();
        $this->assertFalse($t->terjadwal);
        $this->assertSame('Terbit', $t->labelStatus());

        $this->get(route('saya.beranda'))->assertOk()->assertSee('Tugas Jadwal');
        $this->get(route('saya.tugas'))->assertOk()->assertSee('Tugas Jadwal');
    }

    public function test_kasus_yang_dilaporkan_jadwal_10_25_sudah_lewat_jam_12_04_wib(): void
    {
        [$g, $k, $a] = $this->siap();

        $this->actingAs($g)
            ->post(route('tugas.store', $k), $this->isi(['terbit_pada' => '2026-11-22T10:25']))
            ->assertRedirect();

        $t = Tugas::first();
        $this->assertSame('2026-11-22 10:25:00', $t->terbit_pada->format('Y-m-d H:i:s'));
        $this->assertSame('terbit', $t->status);
        $this->assertFalse($t->terjadwal);
        $this->assertTrue($t->terlihat);

        $this->masukSiswa($a);
        $this->get(route('saya.beranda'))->assertOk()->assertSee('Tugas Jadwal');
    }

    public function test_tanpa_jadwal_tetap_draf_dan_update_draf_dengan_jadwal_jadi_terbit(): void
    {
        [$g, $k] = $this->siap();

        $this->actingAs($g)->post(route('tugas.store', $k), $this->isi())->assertRedirect();

        $t = Tugas::first();
        $this->assertSame('draf', $t->status);
        $this->assertFalse($t->terjadwal);

        $this->put(route('tugas.update', $t), $this->isi(['terbit_pada' => '2026-11-22T18:00']))->assertRedirect();
        $t->refresh();
        $this->assertSame('terbit', $t->status);
        $this->assertTrue($t->terjadwal);

        // Jadwal dikosongkan pada tugas yang sudah terbit: tetap terbit, langsung terlihat
        $this->put(route('tugas.update', $t), $this->isi(['terbit_pada' => '']))->assertRedirect();
        $t->refresh();
        $this->assertSame('terbit', $t->status);
        $this->assertNull($t->terbit_pada);
        $this->assertTrue($t->terlihat);
    }

    public function test_arsip_tidak_ikut_berubah_saat_diedit(): void
    {
        [$g, $k] = $this->siap();
        $this->actingAs($g);

        $t = Tugas::create([
            'kelas_id'     => $k->id,
            'judul'        => 'Lama',
            'jenis'        => 'file',
            'tenggat'      => now()->addDay(),
            'status'       => 'arsip',
            'telat'        => 'tutup',
            'boleh_ulang'  => true,
            'maks_berkas'  => 3,
            'ukuran_mb'    => 5,
            'jenis_berkas' => 'umum',
        ]);

        $this->put(route('tugas.update', $t), $this->isi(['terbit_pada' => '2026-11-22T18:00']))->assertRedirect();
        $this->assertSame('arsip', $t->refresh()->status);
        $this->assertSame('Arsip', $t->labelStatus());
    }
}