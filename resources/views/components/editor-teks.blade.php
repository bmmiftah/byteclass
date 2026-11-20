@props(['nama' => 'isi', 'nilai' => '', 'petunjuk' => 'Tulis di sini...'])

@once
    @vite('resources/js/editor.js')
@endonce

<div
    class="editor-teks"
    data-editor
    x-ignore
    data-unggah="{{ route('materi.gambar', [], false) }}"
    data-petunjuk="{{ $petunjuk }}"
>
    <div class="editor-bar" role="toolbar" aria-label="Format teks">
        <div class="ed-grup">
            <button type="button" class="ed-btn" data-aksi="paragraf" title="Paragraf biasa">P</button>
            <button type="button" class="ed-btn" data-aksi="h2" title="Judul besar">H2</button>
            <button type="button" class="ed-btn" data-aksi="h3" title="Judul kecil">H3</button>
        </div>

        <div class="ed-grup">
            <button type="button" class="ed-btn" data-aksi="tebal" title="Tebal"><b>B</b></button>
            <button type="button" class="ed-btn" data-aksi="miring" title="Miring"><i>I</i></button>
            <button type="button" class="ed-btn" data-aksi="garis" title="Garis bawah"><u>U</u></button>
            <button type="button" class="ed-btn" data-aksi="coret" title="Coret"><s>S</s></button>
        </div>

        <div class="ed-grup">
            <button type="button" class="ed-btn" data-aksi="butir" title="Daftar butir">&bull; Butir</button>
            <button type="button" class="ed-btn" data-aksi="nomor" title="Daftar nomor">1. Nomor</button>
            <button type="button" class="ed-btn" data-aksi="kutipan" title="Kutipan">&ldquo; &rdquo;</button>
            <button type="button" class="ed-btn" data-aksi="kode" title="Blok kode">&lt;/&gt;</button>
            <button type="button" class="ed-btn" data-aksi="pemisah" title="Garis pemisah">&mdash;</button>
        </div>

        <div class="ed-grup">
            <button type="button" class="ed-btn" data-aksi="tautan" title="Tautan">Tautan</button>
            <button type="button" class="ed-btn" data-aksi="gambar" title="Sisipkan gambar">Gambar</button>
            <button type="button" class="ed-btn" data-aksi="tabel" title="Sisipkan tabel 3x3">Tabel</button>
        </div>

        <div class="ed-grup" data-hanya-tabel hidden>
            <button type="button" class="ed-btn" data-aksi="baris+" title="Tambah baris di bawah">+ Baris</button>
            <button type="button" class="ed-btn" data-aksi="baris-" title="Hapus baris">- Baris</button>
            <button type="button" class="ed-btn" data-aksi="kolom+" title="Tambah kolom di kanan">+ Kolom</button>
            <button type="button" class="ed-btn" data-aksi="kolom-" title="Hapus kolom">- Kolom</button>
            <button type="button" class="ed-btn" data-aksi="judul-baris" title="Jadikan baris pertama judul / bukan">Baris judul</button>
            <button type="button" class="ed-btn" data-aksi="tabel-" title="Hapus seluruh tabel" style="background: var(--merah)">Hapus tabel</button>
        </div>

        <div class="ed-grup">
            <button type="button" class="ed-btn" data-aksi="batal" title="Urungkan">&larr;</button>
            <button type="button" class="ed-btn" data-aksi="ulang" title="Ulangi">&rarr;</button>
        </div>
    </div>

    <div class="editor-isi" data-area></div>
    <p class="text-xs p-2" data-info hidden style="background: var(--kuning); border-top: 3px solid var(--ink)"></p>
    <input type="file" data-berkas-gambar accept="image/jpeg,image/png,image/webp,image/gif" hidden>
    <textarea name="{{ $nama }}" hidden>{{ $nilai }}</textarea>
</div>