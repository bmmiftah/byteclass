<x-cbt-layout :judul="$ujian->judul">
    <main class="max-w-md mx-auto p-4 py-10 text-center"
          x-data="{
              sisa: {{ (int) $cfg['sisa'] }},
              akhirAt: Date.now() + {{ (int) $cfg['sisa'] }} * 1000,
              init() {
                  setInterval(() => { this.sisa = Math.max(0, Math.round((this.akhirAt - Date.now()) / 1000)); }, 500);
                  setInterval(() => this.cek(), 4000);
              },
              get tampil() { return String(Math.floor(this.sisa / 60)).padStart(2, '0') + ':' + String(this.sisa % 60).padStart(2, '0'); },
              async cek() {
                  try {
                      const r = await fetch('{{ $cfg['urlStatus'] }}', { headers: { 'Accept': 'application/json' } });
                      if (!r.ok) return;
                      const d = await r.json();
                      if (d.selesai) { location.href = '{{ $cfg['urlHasil'] }}'; return; }
                      if (!d.terkunci) { location.reload(); return; }
                      this.akhirAt = Date.now() + d.sisa_detik * 1000;
                  } catch (e) { /* coba lagi */ }
              },
          }">
        <div class="nb-card" style="background: var(--merah)">
            <h1 class="text-3xl mb-2">Ujian dikunci</h1>
            <p>Kamu terlalu sering meninggalkan halaman ujian ({{ $p->pelanggaran }} kali), jadi ujianmu dikunci.</p>
            <p class="mt-3 font-bold">Angkat tangan dan panggil gurumu. Halaman ini terbuka sendiri setelah gurumu membuka kunci.</p>
        </div>

        <p class="mt-6">Waktu ujian tetap berjalan: sisa <b class="judul text-xl" x-text="tampil"></b></p>
    </main>
</x-cbt-layout>
