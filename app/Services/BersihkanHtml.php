<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class BersihkanHtml
{
    private static ?HtmlSanitizer $penyaring = null;

    /**
     * Bersihkan HTML dari editor: hanya tag yang aman (paragraf, judul, daftar, tabel, gambar, tautan).
     * Mengembalikan null bila hasilnya kosong (tidak ada teks, gambar, atau tabel).
     */
    public static function proses(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $bersih = trim(self::penyaring()->sanitize($html));

        // gambar tanpa alamat yang sah (mis. data: atau javascript:) dibuang
        $bersih = preg_replace('~<img(?![^>]*\ssrc=)[^>]*>~i', '', $bersih);
        $teks = html_entity_decode(strip_tags($bersih), ENT_QUOTES | ENT_HTML5);
        $adaTeks = preg_replace('/[\s\x{00A0}]+/u', '', $teks) !== '';
        $adaIsi = $adaTeks || preg_match('/<(img|table|hr)\b/i', $bersih);

        return $adaIsi ? $bersih : null;
    }

    private static function penyaring(): HtmlSanitizer
    {
        return self::$penyaring ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowSafeElements()
                ->allowElement('colgroup')
                ->allowAttribute('colspan', ['td', 'th'])
                ->allowAttribute('rowspan', ['td', 'th'])
                ->allowAttribute('alt', 'img')
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->forceAttribute('a', 'target', '_blank')
        );
    }
}