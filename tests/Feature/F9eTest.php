<?php
namespace Tests\Feature;

require_once __DIR__.'/../stubs.php';

use App\Models\{AkunSiswa, Jawaban, Kelas, Mapel, Pengerjaan, PengerjaanKejadian, Siswa, Soal, Ujian, User};
use App\Services\{Anticurang, KemiripanJawaban, KoreksiUjian};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class F9eTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-11-21 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function guru(string $nama = 'Ani'): User
    {
        $u = User::factory()->create(['name' => $nama, 'email' => strtolower($nama).'@x.id']);
        $u->forceFill(['peran' => 'guru'])->save();

        return $u->refresh();
    }

    /** @return array{0:Kelas,1:Mapel,2:array<int,Siswa>} */
    private function kelas(User $g, int $jumlah = 4): array
    {
        $k = Kelas::create(['guru_id' => $g->id, 'nama' => '7A']);
        $m = Mapel::create(['guru_id' => $g->id, 'nama' => 'IPA']);
        $ss = [];
        for ($i = 1; $i <= $jumlah; $i++) {
            $ss[] = Siswa::create(['kelas_id' => $k->id, 'nama' => 'Siswa '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        return [$k, $m, $ss];
    }

    private function soal(User $g, Mapel $m, string $tipe, string $kunci, int $bobot = 10): Soal
    {
        return Soal::create([
            'guru_id' => $g->id, 'mapel_id' => $m->id, 'tipe' => $tipe, 'teks' => 'Soal '.$tipe.' '.uniqid(),
            'opsi' => $tipe === 'pg' ? ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'] : null, 'kunci' => $kunci, 'bobot' => $bobot,
        ]);
    }

    private function ujian(Kelas $k, Mapel $m, array $soals, array $lebih = []): Ujian
    {
        $u = Ujian::create($lebih + [
            'guru_id' => $k->guru_id, 'kelas_id' => $k->id, 'mapel_id' => $m->id, 'judul' => 'Kuis 1',
            'durasi' => 30, 'token' => 'ABC123', 'status' => 'buka',
        ]);
        $sync = [];
        foreach ($soals as $i => $s) {
            $sync[$s->id] = ['urutan' => $i, 'bobot' => (int) $s->bobot];
        }
        $u->soals()->sync($sync);

        return $u->refresh();
    }

    /** Siswa masuk dengan kodenya, memulai ujian, dan membuka halaman kerjakan (sesi aktif). */
    private function mulai(Ujian $u, Siswa $s): Pengerjaan
    {
        $akun = AkunSiswa::firstOrCreate(['siswa_id' => $s->id], ['kode' => AkunSiswa::kodeBaru()]);
        $this->flushSession();
        $this->post(route('cbt.proses'), ['kode' => $akun->kode])->assertRedirect(route('cbt.daftar'));
        $this->post(route('cbt.mulai', $u), ['token' => $u->token])->assertRedirect(route('cbt.kerjakan', $u));
        $this->get(route('cbt.kerjakan', $u))->assertOk();

        return Pengerjaan::where('ujian_id', $u->id)->where('siswa_id', $s->id)->firstOrFail();
    }

    /** Kirim satu kejadian lalu majukan jam 4 detik, supaya tidak tergabung dengan kejadian berikutnya. */
    private function kejadian(Ujian $u, string $jenis, ?int $detik = null, bool $maju = true)
    {
        $r = $this->postJson(route('cbt.kejadian', $u), array_filter(['jenis' => $jenis, 'detik' => $detik], fn ($v) => $v !== null));
        if ($maju) {
            Carbon::setTestNow(now()->addSeconds(4));
        }

        return $r;
    }

    /** Pengerjaan selesai buatan langsung (tanpa lewat browser), untuk uji kemiripan. $jawab: [soal_id => jawaban]. */
    private function selesai(Ujian $u, Siswa $s, array $jawab): Pengerjaan
    {
        $p = Pengerjaan::create([
            'ujian_id' => $u->id, 'siswa_id' => $s->id, 'mulai_pada' => now(), 'batas_pada' => now()->addHour(),
            'status' => 'berjalan', 'urutan_soal' => $u->soals()->pluck('soals.id')->all(),
        ]);
        foreach ($jawab as $soalId => $j) {
            Jawaban::create(['pengerjaan_id' => $p->id, 'soal_id' => $soalId, 'jawaban' => $j]);
        }
        KoreksiUjian::selesaikan($p);

        return $p->refresh();
    }

    /* ---------- pengaturan guru ---------- */

    public function test_guru_menyimpan_pengaturan_dan_kartu_tampil_di_halaman_edit(): void
    {
        $g = $this->guru();
        [$k, $m] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $this->actingAs($g);

        // bawaan: blokir salin hidup, layar penuh mati, tanpa batas
        $this->assertTrue($u->blokir_salin);
        $this->assertFalse($u->layar_penuh);
        $this->assertNull($u->batas_pelanggaran);
        $this->assertFalse($u->punyaBatas());

        $this->get(route('ujian.edit', $u))->assertOk()->assertSee('Anti-curang')->assertSee('Blokir salin');

        $this->post(route('ujian.anticurang', $u), ['layar_penuh' => 1, 'batas_pelanggaran' => 3, 'aksi_batas' => 'kumpul'])
            ->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertFalse($u->blokir_salin);             // tidak dicentang = mati
        $this->assertTrue($u->layar_penuh);
        $this->assertSame(3, $u->batas_pelanggaran);
        $this->assertSame('kumpul', $u->aksi_batas);
        $this->assertTrue($u->punyaBatas());

        // kosongkan batas = tanpa batas
        $this->post(route('ujian.anticurang', $u), ['blokir_salin' => 1, 'batas_pelanggaran' => '', 'aksi_batas' => 'kunci']);
        $this->assertNull($u->fresh()->batas_pelanggaran);
        $this->assertTrue($u->fresh()->blokir_salin);
    }

    public function test_pengaturan_menolak_isian_tidak_valid(): void
    {
        $g = $this->guru();
        [$k, $m] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $this->actingAs($g);

        foreach ([0, 51, -1, 'abc'] as $salah) {
            $this->post(route('ujian.anticurang', $u), ['batas_pelanggaran' => $salah, 'aksi_batas' => 'kunci'])->assertSessionHasErrors('batas_pelanggaran');
        }
        $this->post(route('ujian.anticurang', $u), ['batas_pelanggaran' => 3, 'aksi_batas' => 'hapus'])->assertSessionHasErrors('aksi_batas');
        $this->post(route('ujian.anticurang', $u), ['batas_pelanggaran' => 3])->assertSessionHasErrors('aksi_batas');
        $this->assertNull($u->fresh()->batas_pelanggaran);
    }

    public function test_guru_lain_tidak_bisa_mengubah_melihat_log_membuka_kunci_atau_cek_kemiripan(): void
    {
        $g = $this->guru('Ani');
        $lain = $this->guru('Budi');
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 1]);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');
        $this->assertTrue($p->fresh()->terkunci());

        $this->flushSession();
        $this->actingAs($lain);
        $this->post(route('ujian.anticurang', $u), ['aksi_batas' => 'kunci'])->assertForbidden();
        $this->get(route('ujian.log', [$u, $p]))->assertForbidden();
        $this->post(route('ujian.bukaKunci', $p))->assertForbidden();
        $this->get(route('ujian.kemiripan', $u))->assertForbidden();
        $this->assertTrue($p->fresh()->terkunci());
    }

    public function test_siswa_melihat_aturan_di_halaman_token(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['blokir_salin' => true, 'layar_penuh' => true, 'batas_pelanggaran' => 3, 'aksi_batas' => 'kumpul']);
        $akun = AkunSiswa::create(['siswa_id' => $ss[0]->id, 'kode' => AkunSiswa::kodeBaru()]);
        $this->post(route('cbt.proses'), ['kode' => $akun->kode]);

        $this->get(route('cbt.token', $u))->assertOk()
            ->assertSee('Aturan ujian')->assertSee('Menyalin, menempel')->assertSee('layar penuh')
            ->assertSee('Batas pelanggaran')->assertSee('dikumpulkan otomatis');

        $u->update(['blokir_salin' => false, 'layar_penuh' => false, 'batas_pelanggaran' => null]);
        $this->get(route('cbt.token', $u))->assertOk()->assertSee('Aturan ujian')
            ->assertDontSee('Menyalin, menempel')->assertDontSee('Batas pelanggaran');
    }

    public function test_halaman_kerjakan_membawa_pengaturan_ke_browser(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['blokir_salin' => true, 'layar_penuh' => true, 'batas_pelanggaran' => 4]);
        $this->mulai($u, $ss[0]);

        $html = $this->get(route('cbt.kerjakan', $u))->assertOk()->getContent();

        // konfigurasi dikirim sebagai JSON.parse('...') dengan tanda kutip ditulis \u0022; bongkar lalu periksa isinya
        $this->assertSame(1, preg_match("/JSON\\.parse\\('(.*?)'\\)/s", $html, $mm));
        $cfg = json_decode(str_replace(chr(92).'u0022', '"', $mm[1]), true);
        $this->assertTrue($cfg['layarPenuh']);
        $this->assertTrue($cfg['blokirSalin']);
        $this->assertFalse($cfg['kumpulOtomatis']);
        $this->assertSame(4, $cfg['batas']);
        $this->assertSame(4, $cfg['sisaKesempatan']);
        $this->assertSame(0, $cfg['pelanggaran']);
        $this->assertStringContainsString('/kejadian', $cfg['urlKejadian']);
    }

    /* ---------- kejadian dan hitungan ---------- */

    public function test_jenis_yang_dihitung_menambah_pelanggaran_dan_tercatat(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        $this->kejadian($u, 'keluar_tab')->assertOk()->assertJsonPath('pelanggaran', 1)->assertJsonPath('dihitung', true);
        $this->kejadian($u, 'blur')->assertJsonPath('pelanggaran', 2);
        $this->kejadian($u, 'keluar_layar_penuh')->assertJsonPath('pelanggaran', 3)->assertJsonPath('terkunci', false);

        $p->refresh();
        $this->assertSame(3, $p->pelanggaran);
        $this->assertSame(3, $p->kejadians()->where('dihitung', true)->count());
        $this->assertSame(['keluar_tab', 'blur', 'keluar_layar_penuh'], $p->kejadians()->pluck('jenis')->all());
    }

    public function test_salin_tempel_dan_kawan_kawan_dicatat_tetapi_tidak_dihitung(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 1]);
        $p = $this->mulai($u, $ss[0]);

        foreach (['salin', 'potong', 'tempel', 'klik_kanan', 'cetak'] as $j) {
            $this->kejadian($u, $j, null, false)->assertOk()->assertJsonPath('dihitung', false)->assertJsonPath('terkunci', false);
        }

        $p->refresh();
        $this->assertSame(0, $p->pelanggaran);
        $this->assertSame(5, $p->kejadians()->count());
        $this->assertSame(0, $p->kejadians()->where('dihitung', true)->count());
        $this->assertFalse($p->terkunci());
    }

    public function test_dua_kejadian_dalam_tiga_detik_dihitung_satu_lalu_dihitung_lagi_setelahnya(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        // pindah tab memicu keluar_tab dan blur hampir bersamaan
        $this->kejadian($u, 'keluar_tab', null, false)->assertJsonPath('pelanggaran', 1);
        Carbon::setTestNow(now()->addSeconds(1));
        $this->kejadian($u, 'blur', null, false)->assertJsonPath('pelanggaran', 1)->assertJsonPath('dihitung', false);
        $this->assertSame(1, $p->kejadians()->count());                        // kembaran tidak dicatat sama sekali

        Carbon::setTestNow(now()->addSeconds(3));                              // total 4 detik sejak yang pertama
        $this->kejadian($u, 'keluar_tab', null, false)->assertJsonPath('pelanggaran', 2);
        $this->assertSame(2, $p->fresh()->pelanggaran);
    }

    public function test_kembali_menyimpan_lama_pergi_dan_tidak_dihitung(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        $this->kejadian($u, 'keluar_tab');
        $this->kejadian($u, 'kembali', 47)->assertJsonPath('pelanggaran', 1)->assertJsonPath('dihitung', false);

        $k2 = $p->kejadians()->where('jenis', 'kembali')->first();
        $this->assertSame('setelah 47 detik', $k2->detail);
    }

    public function test_kejadian_menolak_jenis_asing_dan_detik_tidak_masuk_akal(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'kunci'])->assertStatus(422);              // jenis milik sistem
        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'buka_kunci'])->assertStatus(422);
        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'apa_saja'])->assertStatus(422);
        $this->postJson(route('cbt.kejadian', $u), [])->assertStatus(422);
        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'kembali', 'detik' => -5])->assertStatus(422);
        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'kembali', 'detik' => 999999])->assertStatus(422);
        $this->assertSame(0, $p->kejadians()->count());
    }

    public function test_kejadian_ditolak_dari_perangkat_lama_dan_setelah_selesai_dan_tanpa_login(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);
        $sesiLama = $this->app['session']->all();

        // perangkat lain mengambil alih
        $this->flushSession();
        $akun = AkunSiswa::where('siswa_id', $ss[0]->id)->first();
        $this->post(route('cbt.proses'), ['kode' => $akun->kode]);
        $this->get(route('cbt.kerjakan', $u))->assertOk();
        $this->flushSession();
        $this->withSession($sesiLama);
        $this->kejadian($u, 'keluar_tab')->assertStatus(409);
        $this->assertSame(0, $p->fresh()->pelanggaran);

        // setelah selesai
        $this->flushSession();
        $this->post(route('cbt.proses'), ['kode' => $akun->kode]);
        $this->get(route('cbt.kerjakan', $u))->assertOk();
        $this->post(route('cbt.selesai', $u));
        $this->kejadian($u, 'keluar_tab')->assertStatus(410);
        $this->assertSame(0, $p->fresh()->pelanggaran);

        // tanpa login siswa
        $this->flushSession();
        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'keluar_tab'])->assertStatus(401);
    }

    public function test_layanan_tidak_mencatat_apa_pun_setelah_pengerjaan_selesai(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $soal = $this->soal($g, $m, 'pg', 'A');
        $u = $this->ujian($k, $m, [$soal], ['batas_pelanggaran' => 1]);
        $p = $this->mulai($u, $ss[0]);
        $this->post(route('cbt.selesai', $u));
        $p->refresh();
        $this->assertSame('selesai', $p->status);

        // langsung ke layanan (jalur lain, mis. titik lama), bukan lewat pemeriksaan di controller
        $keadaan = Anticurang::catat($p, 'keluar_tab');
        $this->assertTrue($keadaan['selesai']);
        $this->assertFalse($keadaan['dihitung']);
        $this->assertSame(0, $p->fresh()->pelanggaran);
        $this->assertFalse($p->fresh()->kena_batas);
        $this->assertSame(0, $p->kejadians()->count());
    }

    public function test_siswa_lain_tidak_bisa_mengirim_kejadian_untuk_ujian_yang_bukan_miliknya(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $this->mulai($u, $ss[0]);

        // siswa kedua belum mulai: tidak punya pengerjaan
        $akun = AkunSiswa::create(['siswa_id' => $ss[1]->id, 'kode' => AkunSiswa::kodeBaru()]);
        $this->flushSession();
        $this->post(route('cbt.proses'), ['kode' => $akun->kode]);
        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'keluar_tab'])->assertNotFound();
        $this->assertSame(0, PengerjaanKejadian::count());
    }

    public function test_catatan_dibatasi_tetapi_pelanggaran_yang_dihitung_tetap_masuk(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        $baris = [];
        for ($i = 0; $i < Anticurang::MAKS_CATATAN; $i++) {
            $baris[] = ['pengerjaan_id' => $p->id, 'jenis' => 'salin', 'dihitung' => false, 'terjadi_pada' => now()->subHour()];
        }
        PengerjaanKejadian::insert($baris);

        $this->kejadian($u, 'tempel', null, false)->assertOk();
        $this->assertSame(Anticurang::MAKS_CATATAN, $p->kejadians()->count());      // yang berlebih dibuang

        $this->kejadian($u, 'keluar_tab', null, false)->assertJsonPath('pelanggaran', 1);
        $this->assertSame(1, $p->fresh()->pelanggaran);                             // yang dihitung tidak pernah dibuang
    }

    public function test_titik_lama_pelanggaran_tetap_204_dan_ikut_tercatat(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        $this->postJson(route('cbt.pelanggaran', $u))->assertNoContent();
        $this->assertSame(1, $p->fresh()->pelanggaran);
        $this->assertSame('keluar_tab', $p->kejadians()->first()->jenis);
    }

    /* ---------- batas pelanggaran ---------- */

    public function test_batas_tercapai_mengunci_ujian_dan_soal_tidak_dikirim_lagi(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $soal = $this->soal($g, $m, 'pg', 'A');
        $u = $this->ujian($k, $m, [$soal], ['batas_pelanggaran' => 2, 'aksi_batas' => 'kunci']);
        $p = $this->mulai($u, $ss[0]);

        $this->kejadian($u, 'keluar_tab')->assertJsonPath('terkunci', false)->assertJsonPath('sisa', 1);
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('terkunci', true)->assertJsonPath('sisa', 0);

        $p->refresh();
        $this->assertTrue($p->terkunci());
        $this->assertTrue($p->kena_batas);
        $this->assertSame('berjalan', $p->status);
        $this->assertContains('kunci', $p->kejadians()->pluck('jenis')->all());

        // halaman kerjakan sekarang layar terkunci, tanpa soal
        $r = $this->get(route('cbt.kerjakan', $u))->assertOk()->assertSee('Ujian dikunci');
        $this->assertStringNotContainsString($soal->teks, $r->getContent());
        $this->assertStringNotContainsString('ujianSiswa', $r->getContent());

        // jawaban ditolak (423) dan kejadian baru tidak menambah hitungan
        $this->postJson(route('cbt.jawab', $u), ['soal_id' => $soal->id, 'jawaban' => 'A'])->assertStatus(423);
        $this->assertSame(0, $p->jawabans()->count());
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('pelanggaran', 2)->assertJsonPath('terkunci', true);
        $this->assertSame(2, $p->fresh()->pelanggaran);

        // layar terkunci memantau keadaan lewat status
        $this->getJson(route('cbt.status', $u))->assertOk()->assertJsonPath('terkunci', true)->assertJsonPath('selesai', false);
    }

    public function test_siswa_terkunci_masih_boleh_mengumpulkan(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 1]);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');
        $this->assertTrue($p->fresh()->terkunci());

        $this->post(route('cbt.selesai', $u))->assertRedirect(route('cbt.hasil', $u));
        $this->assertSame('selesai', $p->fresh()->status);
    }

    public function test_batas_dengan_aksi_kumpul_mengumpulkan_otomatis_dan_menilai(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $soal = $this->soal($g, $m, 'pg', 'A');
        $u = $this->ujian($k, $m, [$soal], ['batas_pelanggaran' => 2, 'aksi_batas' => 'kumpul']);
        $p = $this->mulai($u, $ss[0]);
        $this->postJson(route('cbt.jawab', $u), ['soal_id' => $soal->id, 'jawaban' => 'A'])->assertOk();

        $this->kejadian($u, 'keluar_tab')->assertJsonPath('selesai', false);
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('selesai', true)->assertJsonPath('terkunci', false);

        $p->refresh();
        $this->assertSame('selesai', $p->status);
        $this->assertTrue($p->kena_batas);
        $this->assertFalse($p->terkunci());
        $this->assertEqualsWithDelta(100.0, $p->nilai, 0.001);                      // jawaban yang sudah masuk tetap dinilai
        $this->assertContains('kumpul_otomatis', $p->kejadians()->pluck('jenis')->all());

        $this->postJson(route('cbt.jawab', $u), ['soal_id' => $soal->id, 'jawaban' => 'B'])->assertStatus(410);
    }

    public function test_tanpa_batas_tidak_pernah_terkunci_walau_pelanggaran_banyak(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        for ($i = 0; $i < 12; $i++) {
            $this->kejadian($u, 'keluar_tab')->assertJsonPath('terkunci', false)->assertJsonPath('sisa', null);
        }
        $this->assertSame(12, $p->fresh()->pelanggaran);
        $this->assertFalse($p->fresh()->kena_batas);
    }

    public function test_buka_kunci_memberi_jatah_baru_dan_riwayat_tetap(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $soal = $this->soal($g, $m, 'pg', 'A');
        $u = $this->ujian($k, $m, [$soal], ['batas_pelanggaran' => 2, 'aksi_batas' => 'kunci']);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');
        $this->kejadian($u, 'keluar_tab');
        $this->assertTrue($p->fresh()->terkunci());

        // guru membuka kunci
        $this->flushSession();
        $this->actingAs($g);
        $this->post(route('ujian.bukaKunci', $p))->assertRedirect();
        $p->refresh();
        $this->assertFalse($p->terkunci());
        $this->assertSame(2, $p->dasar_batas);
        $this->assertSame(2, $p->pelanggaran);                                       // riwayat tidak dihapus
        $this->assertSame(2, Anticurang::sisaKesempatan($p->load('ujian')));         // jatah baru sebanyak batas
        $this->assertContains('buka_kunci', $p->kejadians()->pluck('jenis')->all());
        $this->assertStringContainsString('Ani', $p->kejadians()->where('jenis', 'buka_kunci')->value('detail'));

        // siswa kembali: satu pelanggaran belum mengunci, dua mengunci lagi
        $this->app['auth']->forgetGuards();
        $akun = AkunSiswa::where('siswa_id', $ss[0]->id)->first();
        $this->flushSession();
        $this->post(route('cbt.proses'), ['kode' => $akun->kode]);
        $this->get(route('cbt.kerjakan', $u))->assertOk()->assertSee($soal->teks);   // soal tampil lagi
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('terkunci', false)->assertJsonPath('sisa', 1)->assertJsonPath('pelanggaran', 3);
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('terkunci', true)->assertJsonPath('pelanggaran', 4);
    }

    public function test_buka_kunci_pada_siswa_tidak_terkunci_tidak_mengubah_apa_apa(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 3]);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');

        $this->flushSession();
        $this->actingAs($g);
        $this->post(route('ujian.bukaKunci', $p))->assertSessionHas('ok');
        $p->refresh();
        $this->assertSame(0, $p->dasar_batas);                                      // jatah tidak ikut diperbarui
        $this->assertSame(0, $p->kejadians()->where('jenis', 'buka_kunci')->count());
        $this->postJson(route('ujian.bukaKunci', $p))->assertOk()->assertJsonPath('ok', false);
    }

    public function test_batas_diubah_guru_berlaku_untuk_kejadian_berikutnya(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 2]);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('sisa', 1);

        $u->update(['batas_pelanggaran' => 5]);
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('terkunci', false)->assertJsonPath('sisa', 3);
    }

    public function test_batas_diubah_ke_lebih_kecil_dari_pelanggaran_yang_ada_mengunci_pada_kejadian_berikutnya(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');
        $this->kejadian($u, 'keluar_tab');
        $this->kejadian($u, 'keluar_tab');

        $u->update(['batas_pelanggaran' => 2]);                                     // sudah terlanjur 3
        $this->kejadian($u, 'keluar_tab')->assertJsonPath('terkunci', true);
    }

    /* ---------- halaman guru ---------- */

    public function test_pantau_json_memuat_status_kunci_dan_halaman_pantau_menampilkan_tombol(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 1]);
        $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');

        $this->flushSession();
        $this->actingAs($g);
        $baris = collect($this->getJson(route('ujian.pantauJson', $u))->assertOk()->json('baris'));
        $a = $baris->firstWhere('nama', 'Siswa 01');
        $this->assertTrue($a['terkunci']);
        $this->assertTrue($a['kena_batas']);
        $this->assertFalse($baris->firstWhere('nama', 'Siswa 02')['terkunci']);

        $this->get(route('ujian.pantau', $u))->assertOk()->assertSee('Buka kunci')->assertSee('Log');
    }

    public function test_halaman_hasil_menandai_pelanggaran_dan_menautkan_log_dan_kemiripan(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 1, 'aksi_batas' => 'kumpul']);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');                                          // dikumpulkan otomatis

        $this->flushSession();
        $this->actingAs($g);
        $this->get(route('ujian.hasil', $u))->assertOk()
            ->assertSee('Kena batas')
            ->assertSee(route('ujian.log', [$u, $p]), false)
            ->assertSee(route('ujian.kemiripan', $u), false);
        $this->get(route('ujian.koreksi', [$u, $p]))->assertOk()->assertSee('Kena batas pelanggaran')->assertSee(route('ujian.log', [$u, $p]), false);
    }

    public function test_halaman_log_menampilkan_kejadian_berurutan_dan_ringkasan(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 3]);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');
        $this->kejadian($u, 'kembali', 30);
        $this->kejadian($u, 'tempel', null, false);

        $this->flushSession();
        $this->actingAs($g);
        $r = $this->get(route('ujian.log', [$u, $p]))->assertOk()
            ->assertSee('Siswa 01')->assertSee('Pindah tab atau aplikasi')->assertSee('Kembali ke halaman ujian')
            ->assertSee('setelah 30 detik')->assertSee('Mencoba menempel')->assertSee('dari batas 3');
        $this->assertLessThan(strpos($r->getContent(), 'Mencoba menempel</td>'), strpos($r->getContent(), 'Pindah tab atau aplikasi</td>'));
    }

    public function test_log_siswa_tanpa_kejadian_dan_log_pengerjaan_ujian_lain_404(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u1 = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $u2 = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['judul' => 'Kuis 2']);
        $p = $this->mulai($u1, $ss[0]);

        $this->flushSession();
        $this->actingAs($g);
        $this->get(route('ujian.log', [$u1, $p]))->assertOk()->assertSee('Tidak ada kejadian tercatat');
        $this->get(route('ujian.log', [$u2, $p]))->assertNotFound();               // pengerjaan bukan dari ujian itu
    }

    public function test_reset_pengerjaan_ikut_menghapus_catatannya(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')], ['batas_pelanggaran' => 1]);
        $p = $this->mulai($u, $ss[0]);
        $this->kejadian($u, 'keluar_tab');
        $this->assertGreaterThan(0, PengerjaanKejadian::count());

        $this->flushSession();
        $this->actingAs($g);
        $this->post(route('ujian.aksi', $p), ['aksi' => 'reset'])->assertRedirect();
        $this->assertSame(0, PengerjaanKejadian::count());
    }

    public function test_log_mencatat_jam_dari_server_bukan_dari_browser(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g);
        $u = $this->ujian($k, $m, [$this->soal($g, $m, 'pg', 'A')]);
        $p = $this->mulai($u, $ss[0]);

        $this->postJson(route('cbt.kejadian', $u), ['jenis' => 'keluar_tab', 'terjadi_pada' => '2000-01-01 00:00:00']);
        $this->assertSame('2026-11-21 10:00:00', $p->kejadians()->first()->terjadi_pada->format('Y-m-d H:i:s'));
    }

    /* ---------- kemiripan: bagian teks ---------- */

    public function test_pembersihan_dan_kemiripan_teks(): void
    {
        $this->assertSame('fotosintesis itu penting', KemiripanJawaban::bersih("  Fotosintesis, ITU...  penting! "));
        $this->assertSame(1.0, KemiripanJawaban::kemiripanTeks('Tumbuhan membuat makanan sendiri dengan cahaya matahari', 'tumbuhan membuat makanan SENDIRI dengan cahaya matahari!'));
        $this->assertSame(0.0, KemiripanJawaban::kemiripanTeks('satu dua tiga empat lima', 'enam tujuh delapan sembilan sepuluh'));
        $sebagian = KemiripanJawaban::kemiripanTeks(
            'tumbuhan hijau membuat makanan sendiri dengan bantuan cahaya matahari di daun',
            'tumbuhan hijau membuat makanan sendiri dengan bantuan sinar surya pada klorofil'
        );
        $this->assertGreaterThan(0.2, $sebagian);
        $this->assertLessThan(0.7, $sebagian);
        $this->assertSame(0.0, KemiripanJawaban::kemiripanTeks('', 'ada isinya di sini ya'));
        $this->assertSame(7, KemiripanJawaban::jumlahKata('satu dua, tiga! empat... lima enam   tujuh'));
    }

    /* ---------- kemiripan: pencarian pasangan ---------- */

    /** @return array{0:User,1:Kelas,2:Mapel,3:array,4:array,5:Ujian} */
    private function ujianPg(int $jumlahSoal = 10, int $siswa = 5): array
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g, $siswa);
        $soals = [];
        for ($i = 0; $i < $jumlahSoal; $i++) {
            $soals[] = $this->soal($g, $m, 'pg', 'A', 1);       // kunci semua A
        }
        $u = $this->ujian($k, $m, $soals);

        return [$g, $k, $m, $ss, $soals, $u];
    }

    private function jawabSemua(array $soals, array $pola): array
    {
        $out = [];
        foreach ($soals as $i => $s) {
            $out[$s->id] = $pola[$i] ?? '';
        }

        return $out;
    }

    public function test_pg_dua_siswa_dengan_jawaban_salah_yang_sama_ditandai(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();

        // A dan B: enam soal sama-sama salah dengan pilihan sama; sisanya benar
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, ['B', 'C', 'D', 'B', 'C', 'D', 'A', 'A', 'A', 'A']));
        $this->selesai($u, $ss[1], $this->jawabSemua($soals, ['B', 'C', 'D', 'B', 'C', 'D', 'A', 'A', 'A', 'A']));
        // C: salah di soal yang sama, tetapi memilih pengecoh yang berbeda
        $this->selesai($u, $ss[2], $this->jawabSemua($soals, ['C', 'D', 'B', 'C', 'D', 'B', 'A', 'A', 'A', 'A']));

        $h = KemiripanJawaban::cari($u);
        $this->assertSame(3, $h['jumlah']);
        $this->assertCount(1, $h['pasangan']);
        $ps = $h['pasangan'][0];
        $this->assertSame(['Siswa 01', 'Siswa 02'], [$ps['a']['nama'], $ps['b']['nama']]);
        $this->assertSame(6, $ps['pg']['sama']);
        $this->assertSame(6, $ps['pg']['dua_salah']);
        $this->assertSame([1, 2, 3, 4, 5, 6], $ps['pg']['soal']);
        $this->assertSame(6, $ps['poin']);
    }

    public function test_pg_jawaban_benar_yang_sama_tidak_pernah_dihitung(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();
        $semuaBenar = $this->jawabSemua($soals, array_fill(0, 10, 'A'));
        $this->selesai($u, $ss[0], $semuaBenar);
        $this->selesai($u, $ss[1], $semuaBenar);

        $this->assertSame([], KemiripanJawaban::cari($u)['pasangan']);
    }

    public function test_pg_kurang_dari_empat_soal_sama_sama_salah_tidak_ditandai(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();
        $pola = ['B', 'B', 'B', 'A', 'A', 'A', 'A', 'A', 'A', 'A'];                   // hanya 3 soal salah, semuanya sama
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, $pola));
        $this->selesai($u, $ss[1], $this->jawabSemua($soals, $pola));

        $this->assertSame([], KemiripanJawaban::cari($u)['pasangan']);
    }

    public function test_pg_ambang_porsi_bisa_dinaikkan(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();
        // sama-sama salah di 8 soal, sama di 6 (75%)
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, ['B', 'B', 'B', 'B', 'B', 'B', 'B', 'B', 'A', 'A']));
        $this->selesai($u, $ss[1], $this->jawabSemua($soals, ['B', 'B', 'B', 'B', 'B', 'B', 'C', 'C', 'A', 'A']));

        $this->assertCount(1, KemiripanJawaban::cari($u, 0.75)['pasangan']);
        $this->assertCount(0, KemiripanJawaban::cari($u, 0.80)['pasangan']);
    }

    public function test_pg_soal_yang_dikosongkan_salah_satu_siswa_tidak_dihitung(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, ['B', 'B', 'B', 'B', 'B', 'B', 'A', 'A', 'A', 'A']));
        $this->selesai($u, $ss[1], $this->jawabSemua($soals, ['B', 'B', 'B', 'B', '', '', 'A', 'A', 'A', 'A']));   // dua dikosongkan

        // yang dikosongkan tidak masuk hitungan: hanya 4 soal sama-sama salah, bukan 6
        $h = KemiripanJawaban::cari($u);
        $this->assertCount(1, $h['pasangan']);
        $this->assertSame(4, $h['pasangan'][0]['pg']['dua_salah']);

        // dikosongkan tiga: tersisa 3 soal sama-sama salah, di bawah minimal 4
        $this->selesai($u, $ss[2], $this->jawabSemua($soals, ['B', 'B', 'B', '', '', '', 'A', 'A', 'A', 'A']));
        $pasangan = collect(KemiripanJawaban::cari($u)['pasangan']);
        $this->assertNull($pasangan->first(fn ($x) => $x['b']['nama'] === 'Siswa 03' || $x['a']['nama'] === 'Siswa 03'));
    }

    public function test_pg_urutan_pilihan_acak_tidak_mengganggu_karena_yang_dibandingkan_huruf_asli(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg(6, 2);
        $a = $this->selesai($u, $ss[0], $this->jawabSemua($soals, ['B', 'B', 'C', 'C', 'D', 'D']));
        $b = $this->selesai($u, $ss[1], $this->jawabSemua($soals, ['B', 'B', 'C', 'C', 'D', 'D']));
        // pengerjaan menyimpan urutan acak berbeda per siswa; tidak ada pengaruhnya
        $a->update(['urutan_opsi' => ['x' => ['D', 'C', 'B', 'A']]]);
        $b->update(['urutan_opsi' => ['x' => ['A', 'B', 'C', 'D']]]);

        $this->assertCount(1, KemiripanJawaban::cari($u)['pasangan']);
    }

    public function test_isian_salah_yang_sama_persis_ditandai_bila_minimal_dua_soal(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g, 4);
        $i1 = $this->soal($g, $m, 'isian', 'fotosintesis');
        $i2 = $this->soal($g, $m, 'isian', 'respirasi');
        $i3 = $this->soal($g, $m, 'isian', 'transpirasi');
        $u = $this->ujian($k, $m, [$i1, $i2, $i3]);

        // A dan B salah sama di dua soal (huruf besar-kecil dan spasi tidak dibedakan), benar di soal ketiga
        $this->selesai($u, $ss[0], [$i1->id => 'fermentasi', $i2->id => 'Osmosis ', $i3->id => 'transpirasi']);
        $this->selesai($u, $ss[1], [$i1->id => 'Fermentasi', $i2->id => 'osmosis', $i3->id => 'transpirasi']);
        // C hanya sama di satu soal
        $this->selesai($u, $ss[2], [$i1->id => 'fermentasi', $i2->id => 'difusi', $i3->id => 'transpirasi']);

        $h = KemiripanJawaban::cari($u);
        $this->assertCount(1, $h['pasangan']);                                       // pasangan A-C dan B-C hanya sama di satu soal
        $this->assertSame(['Siswa 01', 'Siswa 02'], [$h['pasangan'][0]['a']['nama'], $h['pasangan'][0]['b']['nama']]);
        $this->assertCount(2, $h['pasangan'][0]['isian']);
        $this->assertSame(4, $h['pasangan'][0]['poin']);
    }

    public function test_isian_jawaban_benar_yang_sama_tidak_dihitung(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g, 2);
        $i1 = $this->soal($g, $m, 'isian', 'fotosintesis');
        $i2 = $this->soal($g, $m, 'isian', 'respirasi | pernapasan sel');
        $u = $this->ujian($k, $m, [$i1, $i2]);
        $this->selesai($u, $ss[0], [$i1->id => 'fotosintesis', $i2->id => 'respirasi']);
        $this->selesai($u, $ss[1], [$i1->id => 'Fotosintesis', $i2->id => 'respirasi']);

        $this->assertSame([], KemiripanJawaban::cari($u)['pasangan']);
    }

    public function test_uraian_yang_mirip_ditandai_dan_ambangnya_bisa_diubah(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g, 4);
        $e = $this->soal($g, $m, 'esai', 'bebas', 10);
        $u = $this->ujian($k, $m, [$e]);
        $asli = 'Fotosintesis adalah proses tumbuhan hijau membuat makanan sendiri dengan bantuan cahaya matahari dan klorofil di daun';

        $this->selesai($u, $ss[0], [$e->id => $asli]);
        $this->selesai($u, $ss[1], [$e->id => strtoupper($asli).'!!!']);                           // salinan dengan huruf kapital
        $this->selesai($u, $ss[2], [$e->id => 'Tumbuhan hijau mengubah energi cahaya menjadi energi kimia berupa glukosa melalui klorofil di dalam kloroplas']);

        $h = KemiripanJawaban::cari($u);
        $this->assertCount(1, $h['pasangan']);
        $this->assertSame(100, $h['pasangan'][0]['esai'][0]['persen']);
        $this->assertSame(1, $h['pasangan'][0]['esai'][0]['no']);
        $this->assertSame(3, $h['pasangan'][0]['poin']);
        $this->assertCount(1, KemiripanJawaban::cari($u, 0.75, 1.0)['pasangan']);                  // 100% masih lolos ambang 1,0
    }

    public function test_uraian_pendek_atau_kosong_tidak_dibandingkan(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g, 3);
        $e = $this->soal($g, $m, 'esai', 'bebas', 10);
        $u = $this->ujian($k, $m, [$e]);
        $this->selesai($u, $ss[0], [$e->id => 'iya benar sekali']);
        $this->selesai($u, $ss[1], [$e->id => 'iya benar sekali']);
        $this->selesai($u, $ss[2], [$e->id => '']);

        $this->assertSame([], KemiripanJawaban::cari($u)['pasangan']);
    }

    public function test_hanya_pengerjaan_selesai_yang_dibandingkan(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();
        $pola = ['B', 'B', 'B', 'B', 'B', 'B', 'A', 'A', 'A', 'A'];
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, $pola));
        $b = $this->selesai($u, $ss[1], $this->jawabSemua($soals, $pola));
        $this->assertCount(1, KemiripanJawaban::cari($u)['pasangan']);

        $b->forceFill(['status' => 'berjalan'])->save();
        $h = KemiripanJawaban::cari($u);
        $this->assertSame(1, $h['jumlah']);
        $this->assertSame([], $h['pasangan']);
    }

    public function test_pasangan_diurutkan_dari_bukti_terbanyak(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg(10, 4);
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, ['B', 'B', 'B', 'B', 'B', 'B', 'B', 'B', 'B', 'A']));
        $this->selesai($u, $ss[1], $this->jawabSemua($soals, ['B', 'B', 'B', 'B', 'B', 'B', 'B', 'B', 'B', 'A']));      // sama di 9
        $this->selesai($u, $ss[2], $this->jawabSemua($soals, ['C', 'C', 'C', 'C', 'A', 'A', 'A', 'A', 'A', 'A']));
        $this->selesai($u, $ss[3], $this->jawabSemua($soals, ['C', 'C', 'C', 'C', 'A', 'A', 'A', 'A', 'A', 'A']));      // sama di 4

        $h = KemiripanJawaban::cari($u);
        $this->assertCount(2, $h['pasangan']);
        $this->assertSame(9, $h['pasangan'][0]['poin']);
        $this->assertSame(4, $h['pasangan'][1]['poin']);
    }

    /* ---------- kemiripan: halaman ---------- */

    public function test_halaman_kemiripan_menampilkan_pasangan_dan_peringatan_bukan_bukti(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();
        $pola = ['B', 'B', 'B', 'B', 'B', 'B', 'A', 'A', 'A', 'A'];
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, $pola));
        $this->selesai($u, $ss[1], $this->jawabSemua($soals, $pola));
        $this->actingAs($g);

        $this->get(route('ujian.kemiripan', $u))->assertOk()
            ->assertSee('bukan bukti kecurangan')->assertSee('Siswa 01')->assertSee('Siswa 02')
            ->assertSee('<b>6</b> dari 6 soal yang sama-sama salah', false);
    }

    public function test_halaman_kemiripan_kosong_dan_ambang_dari_alamat_dibatasi(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg();
        $this->actingAs($g);

        $this->get(route('ujian.kemiripan', $u))->assertOk()->assertSee('Tidak ada pasangan yang melewati ambang');
        // nilai ekstrem dipotong ke batas wajar, tidak error
        $this->get(route('ujian.kemiripan', [$u, 'pg' => 0.01, 'esai' => 9]))->assertOk()
            ->assertSee('value="0.5"', false)->assertSee('value="1"', false);
        $this->get(route('ujian.kemiripan', [$u, 'pg' => 'abc']))->assertOk();
    }

    public function test_teks_uraian_siswa_di_halaman_kemiripan_di_escape(): void
    {
        $g = $this->guru();
        [$k, $m, $ss] = $this->kelas($g, 2);
        $e = $this->soal($g, $m, 'esai', 'bebas', 10);
        $u = $this->ujian($k, $m, [$e]);
        $jahat = 'halo ini jawaban panjang <script>alert(1)</script> yang sama persis dengan temannya ya';
        $this->selesai($u, $ss[0], [$e->id => $jahat]);
        $this->selesai($u, $ss[1], [$e->id => $jahat]);
        $this->actingAs($g);

        $html = $this->get(route('ujian.kemiripan', $u))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_ujian_remedial_dengan_peserta_terbatas_tetap_bisa_dicek(): void
    {
        [$g, $k, $m, $ss, $soals, $u] = $this->ujianPg(6, 3);
        $u->pesertas()->attach([$ss[0]->id, $ss[1]->id]);
        $pola = ['B', 'B', 'B', 'B', 'B', 'B'];
        $this->selesai($u, $ss[0], $this->jawabSemua($soals, $pola));
        $this->selesai($u, $ss[1], $this->jawabSemua($soals, $pola));

        $this->assertCount(1, KemiripanJawaban::cari($u)['pasangan']);
    }
}
