<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use	App\Models\Concerns\MilikGuru;

class MateriItem extends Model
{
    use MilikGuru;

    protected string $pemilikVia = 'bab.kelas';
    
    protected $fillable = [
        'bab_id',
        'tipe',
        'judul',
        'path',
        'nama_asli',
        'ekstensi',
        'ukuran',
        'url',
        'isi',
        'isi_format',
        'urutan',
    ];

    public function bab()
    {
        return $this->belongsTo(MateriBab::class, 'bab_id');
    }

    // URL tertanam untuk YouTube / Google Drive; null bila bukan keduanya
    public function getEmbedAttribute(): ?string
    {
        if (! $this->url) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $this->url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        if (preg_match('~drive\.google\.com/file/d/([A-Za-z0-9_-]+)~', $this->url, $m)) {
            return 'https://drive.google.com/file/d/'.$m[1].'/preview';
        }

        return null;
    }

    // pdf | gambar | dokumen
    public function getJenisBerkasAttribute(): string
    {
        $e = strtolower((string) $this->ekstensi);

        return match (true) {
            $e === 'pdf' => 'pdf',
            in_array($e, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) => 'gambar',
            default => 'dokumen',
        };
    }

    public function label(): string
    {
        return match ($this->tipe) {
            'video' => 'VIDEO',
            'link' => 'LINK',
            'teks' => 'TEKS',
            default => strtoupper(substr((string) $this->ekstensi, 0, 4)) ?: 'FILE',
        };
    }

    // Isi teks / penjelasan sebagai HTML siap tampil.
    // Materi lama (Markdown) dikonversi; materi baru (HTML dari editor) sudah dibersihkan saat disimpan.
    public function getIsiHtmlAttribute(): ?string
    {
        if (blank($this->isi)) {
            return null;
        }

        if ($this->isi_format === 'html') {
            return $this->isi;
        }

        return Str::markdown($this->isi, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    // Cuplikan teks polos untuk daftar guru
    public function ringkasan(int $batas = 60): string
    {
        $teks = html_entity_decode(strip_tags((string) $this->isi_html), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $teks)), $batas);
    }
}