<?php

namespace App\Services;

use App\Models\LanggananPush;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Psr\Log\AbstractLogger;

/**
 * Satu-satunya tempat yang benar-benar mengirim notifikasi push.
 * Dibuat sebagai kelas tersendiri supaya tes bisa menggantinya dengan pengirim palsu.
 */
class PengirimWebPush
{
    /** Klien HTTP boleh disuntikkan (dipakai tes); bawaannya Guzzle dengan batas waktu pendek. */
    public function __construct(private ?ClientInterface $klien = null)
    {
    }

    public function siap(): bool
    {
        return filled(config('pengingat.vapid.public')) && filled(config('pengingat.vapid.private'));
    }

    /**
     * @param  array{judul:string, isi:string, url:string, tag?:string, badge?:int}  $isi
     * @return 'ok'|'kadaluarsa'|'gagal'
     */
    public function kirim(LanggananPush $langganan, array $isi, int $ttl = 3600): string
    {
        try {
            $web = new WebPush(
                ['VAPID' => [
                    'subject'    => config('pengingat.vapid.subject'),
                    'publicKey'  => config('pengingat.vapid.public'),
                    'privateKey' => config('pengingat.vapid.private'),
                ]],
                ['TTL' => $ttl, 'urgency' => 'normal'],
                $this->klien ?? new Client(['timeout' => 10, 'connect_timeout' => 5]),
                logger: self::pencatat(),
            );

            $laporan = $web->sendOneNotification(
                Subscription::create([
                    'endpoint' => $langganan->endpoint,
                    'keys'     => ['p256dh' => $langganan->p256dh, 'auth' => $langganan->auth],
                    // Pustaka ini bawaannya format lama "aesgcm"; Safari/iPhone hanya mau "aes128gcm" (standar RFC 8291)
                    'contentEncoding' => 'aes128gcm',
                ]),
                json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        } catch (\Throwable $e) {
            Log::warning('Push gagal dikirim: ' . $e->getMessage());

            return 'gagal';
        }

        if ($laporan->isSuccess()) {
            return 'ok';
        }

        if ($laporan->isSubscriptionExpired()) {
            return 'kadaluarsa';
        }

        Log::warning('Push ditolak layanan push: ' . $laporan->getReason());

        return 'gagal';
    }

    /**
     * Pustaka web-push memicu "notice" PHP bila ekstensi GMP/BCMath tidak ada (hanya saran kecepatan).
     * Di Laravel, notice PHP berubah jadi exception dan pengiriman gagal. Dengan pencatat ini, notice
     * cukup dibuang dan hanya peringatan sungguhan yang masuk ke log.
     */
    private static function pencatat(): AbstractLogger
    {
        return new class extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if (in_array($level, ['warning', 'error', 'critical', 'alert', 'emergency'], true)) {
                    Log::log($level, 'web-push: ' . $message);
                }
            }
        };
    }
}