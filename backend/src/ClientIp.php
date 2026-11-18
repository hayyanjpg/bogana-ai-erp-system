<?php

declare(strict_types=1);

namespace Bogana;

/**
 * Menentukan IP klien asli dari header proxy (Vercel) agar bisa diteruskan ke
 * AI service sebagai dasar pembatasan laju per pengunjung.
 *
 * Header bisa dipalsukan oleh klien, sehingga AI service hanya memercayai
 * `X-Client-IP` bila permintaan datang dengan token internal yang sah.
 */
final class ClientIp
{
    /**
     * @param array<string, mixed> $server Biasanya $_SERVER.
     * @return string IP valid, atau string kosong bila tidak ada yang valid.
     */
    public static function resolve(array $server): string
    {
        // X-Real-IP diisi langsung oleh edge Vercel dan tidak bisa dipalsukan
        // klien, jadi diprioritaskan di atas X-Forwarded-For.
        $realIp = $server['HTTP_X_REAL_IP'] ?? null;
        if (is_string($realIp)) {
            $ip = trim($realIp);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($forwarded) && trim($forwarded) !== '') {
            // Ambil entri terakhir: proxy terdekat menambahkan IP klien di
            // ujung, sehingga entri awal tidak bisa dipercaya bila dipalsukan.
            $parts = array_filter(array_map('trim', explode(',', $forwarded)));
            $ip = end($parts);
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        $remote = $server['REMOTE_ADDR'] ?? null;
        if (is_string($remote)) {
            $ip = trim($remote);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '';
    }
}
