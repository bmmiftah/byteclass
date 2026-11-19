<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MateriItem extends Model
{
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
}