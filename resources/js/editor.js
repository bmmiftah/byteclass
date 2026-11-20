import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';
import Placeholder from '@tiptap/extension-placeholder';

// Aksi tombol toolbar: [perintah, nama untuk status aktif]
const AKSI = {
    h2: (c) => c.toggleHeading({ level: 2 }),
    h3: (c) => c.toggleHeading({ level: 3 }),
    paragraf: (c) => c.setParagraph(),
    tebal: (c) => c.toggleBold(),
    miring: (c) => c.toggleItalic(),
    garis: (c) => c.toggleUnderline(),
    coret: (c) => c.toggleStrike(),
    butir: (c) => c.toggleBulletList(),
    nomor: (c) => c.toggleOrderedList(),
    kutipan: (c) => c.toggleBlockquote(),
    kode: (c) => c.toggleCodeBlock(),
    pemisah: (c) => c.setHorizontalRule(),
    tabel: (c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }),
    'baris+': (c) => c.addRowAfter(),
    'baris-': (c) => c.deleteRow(),
    'kolom+': (c) => c.addColumnAfter(),
    'kolom-': (c) => c.deleteColumn(),
    'judul-baris': (c) => c.toggleHeaderRow(),
    'tabel-': (c) => c.deleteTable(),
    batal: (c) => c.undo(),
    ulang: (c) => c.redo(),
};

const STATUS = {
    h2: ['heading', { level: 2 }],
    h3: ['heading', { level: 3 }],
    tebal: ['bold'],
    miring: ['italic'],
    garis: ['underline'],
    coret: ['strike'],
    butir: ['bulletList'],
    nomor: ['orderedList'],
    kutipan: ['blockquote'],
    kode: ['codeBlock'],
    tautan: ['link'],
};

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function unggahGambar(file, kotak) {
    const data = new FormData();
    data.append('gambar', file);

    const res = await fetch(kotak.dataset.unggah, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: data,
    });

    if (!res.ok) {
        let pesan = 'Gagal mengunggah gambar.';

        try {
            pesan = Object.values((await res.json()).errors ?? {})[0]?.[0] ?? pesan;
        } catch (e) {
            /* abaikan */
        }

        throw new Error(pesan);
    }

    return (await res.json()).url;
}

function pasangEditor(kotak) {
    if (kotak.dataset.siap) return;
    kotak.dataset.siap = '1';

    const area = kotak.querySelector('[data-area]');
    const nilai = kotak.querySelector('textarea');
    const info = kotak.querySelector('[data-info]');
    const input = kotak.querySelector('[data-berkas-gambar]');
    const kabar = (teks) => {
        if (info) {
            info.textContent = teks;
            info.hidden = !teks;
        }
    };

    async function sisipkan(file) {
        if (!file || !file.type.startsWith('image/')) return;
        kabar('Mengunggah gambar...');

        try {
            const url = await unggahGambar(file, kotak);
            editor.chain().focus().setImage({ src: url, alt: '' }).run();
            kabar('');
        } catch (e) {
            kabar(e.message);
        }
    }

    const editor = new Editor({
        element: area,
        content: nilai.value || '',
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3] } }),
            Underline,
            Link.configure({
                openOnClick: false,
                autolink: true,
                HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' },
            }),
            Image,
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
            Placeholder.configure({ placeholder: kotak.dataset.petunjuk || 'Tulis di sini...' }),
        ],
        editorProps: {
            attributes: { class: 'teks-materi', role: 'textbox', 'aria-multiline': 'true' },
            handlePaste(view, event) {
                const f = [...(event.clipboardData?.files ?? [])].find((x) => x.type.startsWith('image/'));
                if (!f) return false;
                event.preventDefault();
                sisipkan(f);
                return true;
            },
            handleDrop(view, event) {
                const f = [...(event.dataTransfer?.files ?? [])].find((x) => x.type.startsWith('image/'));
                if (!f) return false;
                event.preventDefault();
                sisipkan(f);
                return true;
            },
        },
        onUpdate({ editor }) {
            nilai.value = editor.isEmpty ? '' : editor.getHTML();
        },
        onTransaction() {
            segarkan();
        },
    });

    function segarkan() {
        const dalamTabel = editor.isActive('table');

        kotak.querySelectorAll('[data-aksi]').forEach((tb) => {
            const nama = tb.dataset.aksi;
            const s = STATUS[nama];
            tb.classList.toggle('aktif', !!s && editor.isActive(...s));
        });

        kotak.querySelectorAll('[data-hanya-tabel]').forEach((el) => {
            el.hidden = !dalamTabel;
        });
    }

    kotak.addEventListener('mousedown', (e) => {
        // jangan sampai tombol mencuri fokus dari editor
        if (e.target.closest('[data-aksi]')) e.preventDefault();
    });

    kotak.addEventListener('click', (e) => {
        const tb = e.target.closest('[data-aksi]');
        if (!tb) return;

        const nama = tb.dataset.aksi;

        if (nama === 'tautan') {
            const lama = editor.getAttributes('link').href ?? '';
            let url = window.prompt('Alamat tautan (kosongkan untuk menghapus tautan):', lama);
            if (url === null) return;
            url = url.trim();

            if (url === '') {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();
                return;
            }

            if (!/^(https?:|mailto:|\/)/i.test(url)) url = 'https://' + url;
            editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        } else if (nama === 'gambar') {
            input.click();
        } else if (AKSI[nama]) {
            AKSI[nama](editor.chain().focus()).run();
        }
    });

    input.addEventListener('change', () => {
        [...input.files].forEach(sisipkan);
        input.value = '';
    });

    // pastikan isi terkirim walau tidak ada ketikan terakhir
    kotak.closest('form')?.addEventListener('submit', () => {
        nilai.value = editor.isEmpty ? '' : editor.getHTML();
    });

    kotak.editor = editor;
    segarkan();
}

function mulai() {
    document.querySelectorAll('[data-editor]').forEach(pasangEditor);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mulai);
} else {
    mulai();
}