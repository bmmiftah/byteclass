<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Menyusun berkas kalender iCalendar (.ics) sesuai RFC 5545:
 * baris diakhiri CRLF, teks di-escape, dan baris panjang dilipat (maks 75 byte per baris).
 */
class IcsPembangun
{
    /**
     * @param  array<int, array{uid:string, judul:string, mulai:Carbon, selesai:?Carbon, seharian:bool, keterangan:?string, url:?string, alarm:array<int,int>}>  $acara
     */
    public static function buat(string $namaKalender, array $acara, ?Carbon $sekarang = null): string
    {
        $sekarang = ($sekarang ?? now())->copy()->utc();

        $baris = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Asisten Guru//Kalender Kelas//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escape($namaKalender),
            'X-WR-TIMEZONE:Asia/Jakarta',
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($acara as $a) {
            $baris[] = 'BEGIN:VEVENT';
            $baris[] = 'UID:' . $a['uid'];
            $baris[] = 'DTSTAMP:' . $sekarang->format('Ymd\THis\Z');

            if ($a['seharian']) {
                $awal = $a['mulai']->copy()->startOfDay();
                $akhir = ($a['selesai'] ?? $a['mulai'])->copy()->startOfDay()->addDay(); // DTEND tanggal itu tidak ikut

                if ($akhir->lte($awal)) {
                    $akhir = $awal->copy()->addDay();
                }

                $baris[] = 'DTSTART;VALUE=DATE:' . $awal->format('Ymd');
                $baris[] = 'DTEND;VALUE=DATE:' . $akhir->format('Ymd');
            } else {
                $awal = $a['mulai']->copy()->utc();
                $akhir = ($a['selesai'] && $a['selesai']->gt($a['mulai'])
                    ? $a['selesai']
                    : $a['mulai']->copy()->addMinutes(15)
                )->copy()->utc();

                $baris[] = 'DTSTART:' . $awal->format('Ymd\THis\Z');
                $baris[] = 'DTEND:' . $akhir->format('Ymd\THis\Z');
            }

            $baris[] = 'SUMMARY:' . self::escape($a['judul']);

            $isi = trim(($a['keterangan'] ?? '') . ($a['url'] ? "\n" . $a['url'] : ''));
            if ($isi !== '') {
                $baris[] = 'DESCRIPTION:' . self::escape($isi);
            }

            if (! empty($a['url'])) {
                $baris[] = 'URL:' . $a['url'];
            }

            foreach ($a['alarm'] as $menit) {
                $baris[] = 'BEGIN:VALARM';
                $baris[] = 'ACTION:DISPLAY';
                $baris[] = 'DESCRIPTION:' . self::escape($a['judul']);
                $baris[] = 'TRIGGER:-PT' . max(1, (int) $menit) . 'M';
                $baris[] = 'END:VALARM';
            }

            $baris[] = 'END:VEVENT';
        }

        $baris[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'lipat'], $baris)) . "\r\n";
    }

    /** Karakter khusus iCalendar: \ ; , dan baris baru. */
    public static function escape(string $teks): string
    {
        $teks = str_replace(["\r\n", "\r"], "\n", $teks);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $teks);
    }

    /** Lipat baris > 75 byte: sambungan diawali satu spasi, dan tidak memotong karakter UTF-8 di tengah. */
    public static function lipat(string $baris): string
    {
        if (strlen($baris) <= 75) {
            return $baris;
        }

        $hasil = [];
        $sisa = $baris;
        $batas = 75;

        while (strlen($sisa) > $batas) {
            $potong = $batas;

            // Mundur sampai di batas karakter (byte lanjutan UTF-8 berbentuk 10xxxxxx)
            while ($potong > 0 && (ord($sisa[$potong]) & 0xC0) === 0x80) {
                $potong--;
            }

            $hasil[] = substr($sisa, 0, $potong);
            $sisa = substr($sisa, $potong);
            $batas = 74; // Baris sambungan diawali 1 spasi
        }

        $hasil[] = $sisa;

        return implode("\r\n ", $hasil);
    }
}