<?php

namespace App\Services;

class KompresGambar
{
    /**
     * Kecilkan dan kompres gambar di tempat (JPG, PNG, WEBP).
     * Aman: jika ada yang gagal, file asli dibiarkan.
     */
    public static function proses(string $path, int $lebarMaks = 1600): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return; // ekstensi GD tidak aktif
        }

        $info = @getimagesize($path);
        if (! $info) {
            return;
        }

        $tipe = $info[2];
        $im = match ($tipe) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (! $im) {
            return;
        }

        // foto HP sering "miring" lewat tag EXIF; luruskan sebelum EXIF terbuang
        if ($tipe === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $sudut = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;

            if ($sudut) {
                $putar = imagerotate($im, $sudut, 0);

                if ($putar) {
                    imagedestroy($im);
                    $im = $putar;
                }
            }
        }

        if ($tipe !== IMAGETYPE_JPEG) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
        }

        if (imagesx($im) > $lebarMaks) {
            $kecil = imagescale($im, $lebarMaks);

            if ($kecil) {
                imagedestroy($im);
                $im = $kecil;

                if ($tipe !== IMAGETYPE_JPEG) {
                    imagealphablending($im, false);
                    imagesavealpha($im, true);
                }
            }
        }

        match ($tipe) {
            IMAGETYPE_JPEG => imagejpeg($im, $path, 82),
            IMAGETYPE_PNG => imagepng($im, $path, 6),
            IMAGETYPE_WEBP => imagewebp($im, $path, 82),
        };

        imagedestroy($im);
    }
}