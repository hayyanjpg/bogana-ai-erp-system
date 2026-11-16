<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('format_number')) {
    function format_number(float|int $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (!function_exists('format_quantity')) {
    function format_quantity(float|int $value): string
    {
        $formatted = number_format((float) $value, 3, ',', '.');
        if (str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted;
    }
}

if (!function_exists('format_date_id')) {
    /**
     * Format ISO date (Y-m-d) menjadi label bahasa Indonesia.
     */
    function format_date_id(?string $value, bool $withWeekday = false): string
    {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            return $value;
        }

        $months = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];
        $weekdays = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];

        $label = $date->format('j') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
        if ($withWeekday) {
            $label = ($weekdays[$date->format('l')] ?? $date->format('l')) . ', ' . $label;
        }

        return $label;
    }
}

if (!function_exists('format_datetime_id')) {
    /**
     * Format timestamp ISO-8601 ke zona waktu operasional (WIB).
     */
    function format_datetime_id(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        try {
            $date = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Jakarta'));
        } catch (Exception) {
            return $value;
        }

        return format_date_id($date->format('Y-m-d')) . ' · ' . $date->format('H:i') . ' WIB';
    }
}

if (!function_exists('status_label')) {
    function status_label(?string $status): string
    {
        $key = strtolower(trim((string) $status));
        if ($key === '') {
            return '-';
        }

        $labels = [
            'received' => 'Diterima',
            'parsed' => 'Terurai',
            'confirmed' => 'Dikonfirmasi',
            'short' => 'Stok kurang',
            'qc_hold' => 'Tahan QC',
            'fulfilled' => 'Selesai',
            'parse_failed' => 'Gagal parse',
            'cancelled' => 'Dibatalkan',
            'pending' => 'Menunggu',
            'allocated' => 'Dialokasikan',
            'ok' => 'Aman',
            'low' => 'Menipis',
            'out' => 'Habis',
            'degraded' => 'Terdegradasi',
            'down' => 'Mati',
            'pass' => 'Lulus',
            'fail' => 'Gagal',
            'hold' => 'Ditahan',
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}

if (!function_exists('status_tone')) {
    function status_tone(?string $status): string
    {
        $key = strtolower(trim((string) $status));

        return match ($key) {
            'ok', 'fulfilled', 'confirmed', 'pass' => 'positive',
            'low', 'short', 'qc_hold', 'hold', 'degraded' => 'warning',
            'out', 'down', 'fail', 'cancelled', 'parse_failed' => 'danger',
            'pending', 'allocated', 'received', 'parsed' => 'neutral',
            default => 'neutral',
        };
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            if ($part !== '') {
                $letters .= strtoupper(substr($part, 0, 1));
            }
        }

        return $letters !== '' ? $letters : '?';
    }
}

if (!function_exists('stock_percent')) {
    /**
     * Skala gauge stok: 100% saat stok dua kali reorder point.
     */
    function stock_percent(float|int $stock, float|int $reorderPoint): float
    {
        $target = (float) $reorderPoint > 0 ? (float) $reorderPoint * 2 : 1.0;

        return max(0.0, min(100.0, ((float) $stock / $target) * 100));
    }
}

if (!function_exists('confidence_tone')) {
    function confidence_tone(float|int $confidence): string
    {
        $value = (float) $confidence;

        return match (true) {
            $value >= 0.75 => 'positive',
            $value >= 0.55 => 'warning',
            default => 'danger',
        };
    }
}

if (!function_exists('confidence_label')) {
    function confidence_label(float|int $confidence): string
    {
        $value = (float) $confidence;

        return match (true) {
            $value >= 0.75 => 'Tinggi',
            $value >= 0.55 => 'Sedang',
            default => 'Rendah',
        };
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Token double-submit cookie untuk form state-changing.
     */
    function csrf_token(): string
    {
        static $token = null;
        if (is_string($token)) {
            return $token;
        }

        $cookie = $_COOKIE['bogana_csrf'] ?? '';
        if (is_string($cookie) && preg_match('/^[a-f0-9]{64}$/', $cookie) === 1) {
            $token = $cookie;

            return $token;
        }

        $token = bin2hex(random_bytes(32));
        if (!headers_sent()) {
            setcookie('bogana_csrf', $token, [
                'expires' => time() + 86400,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }

        return $token;
    }
}

if (!function_exists('csrf_valid')) {
    function csrf_valid(): bool
    {
        $posted = $_POST['csrf_token'] ?? '';
        $cookie = $_COOKIE['bogana_csrf'] ?? '';

        return is_string($posted)
            && is_string($cookie)
            && $cookie !== ''
            && hash_equals($cookie, $posted);
    }
}
