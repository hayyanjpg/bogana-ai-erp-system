<?php

declare(strict_types=1);

namespace Bogana;

/**
 * Kontrol akses dashboard ERP.
 *
 * Mode akses (`DASHBOARD_ACCESS_MODE`):
 * - `required`: wajib login. Butuh `DASHBOARD_PASSWORD_HASH` atau
 *   `DASHBOARD_PASSWORD`. Jika kredensial kosong, dashboard menolak melayani
 *   (fail closed) daripada membiarkan akses tulis terbuka.
 * - `demo`: publik, hanya baca. Semua aksi tulis ditolak di server.
 * - `open`: publik dan boleh menulis. Khusus pengembangan lokal.
 * - `auto` (default): `required` bila kredensial tersedia, jika tidak `demo`.
 *
 * Sesi disimpan sebagai cookie bertanda tangan HMAC, bukan session file,
 * supaya tetap konsisten pada runtime serverless Vercel.
 */
final class Auth
{
    public const MODE_AUTO = 'auto';
    public const MODE_REQUIRED = 'required';
    public const MODE_DEMO = 'demo';
    public const MODE_OPEN = 'open';

    private const COOKIE = 'bogana_auth';
    private const DEFAULT_USERNAME = 'admin';
    private const DEFAULT_TTL = 28800;

    private function __construct(
        private readonly string $mode,
        private readonly string $username,
        private readonly string $passwordHash,
        private readonly string $password,
        private readonly string $sessionSecret,
        private readonly int $ttl,
    ) {
    }

    public static function fromEnv(): self
    {
        $mode = strtolower(trim((string) (getenv('DASHBOARD_ACCESS_MODE') ?: self::MODE_AUTO)));
        if (!in_array($mode, [self::MODE_AUTO, self::MODE_REQUIRED, self::MODE_DEMO, self::MODE_OPEN], true)) {
            $mode = self::MODE_AUTO;
        }

        $passwordHash = trim((string) (getenv('DASHBOARD_PASSWORD_HASH') ?: ''));
        $password = (string) (getenv('DASHBOARD_PASSWORD') ?: '');

        if ($mode === self::MODE_AUTO) {
            $mode = $passwordHash !== '' || $password !== ''
                ? self::MODE_REQUIRED
                : self::MODE_DEMO;
        }

        $username = trim((string) (getenv('DASHBOARD_USER') ?: self::DEFAULT_USERNAME));
        if ($username === '') {
            $username = self::DEFAULT_USERNAME;
        }

        $ttl = filter_var(
            getenv('DASHBOARD_SESSION_TTL') ?: (string) self::DEFAULT_TTL,
            FILTER_VALIDATE_INT,
        );
        if ($ttl === false || $ttl < 300 || $ttl > 604800) {
            $ttl = self::DEFAULT_TTL;
        }

        return new self(
            $mode,
            $username,
            $passwordHash,
            $password,
            trim((string) (getenv('DASHBOARD_SESSION_SECRET') ?: '')),
            $ttl,
        );
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function requiresLogin(): bool
    {
        return $this->mode === self::MODE_REQUIRED;
    }

    public function isReadOnly(): bool
    {
        return $this->mode === self::MODE_DEMO;
    }

    /**
     * Aksi POST yang aman dijalankan pada mode demo karena tidak menulis
     * ke database: urai pesanan (cache Redis) dan cek kecukupan stok.
     */
    public static function demoAllowsAction(string $page, string $action): bool
    {
        return $page === 'orders' && in_array($action, ['parse', 'check'], true);
    }

    /**
     * Mode `required` tanpa kredensial: konfigurasi tidak lengkap.
     */
    public function misconfigured(): bool
    {
        return $this->mode === self::MODE_REQUIRED && !$this->hasCredentials();
    }

    public function isAuthenticated(): bool
    {
        if (!$this->requiresLogin()) {
            return false;
        }

        $token = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($token) || $token === '') {
            return false;
        }

        return $this->readToken($token) === $this->username;
    }

    public function displayName(): string
    {
        if ($this->isAuthenticated()) {
            return $this->username;
        }

        return $this->isReadOnly() ? 'Mode Demo' : 'Tamu';
    }

    public function attempt(string $username, string $password): bool
    {
        if (!$this->hasCredentials()) {
            return false;
        }

        if (!hash_equals($this->username, $username)) {
            return false;
        }

        if ($this->passwordHash !== '') {
            return password_verify($password, $this->passwordHash);
        }

        return hash_equals($this->password, $password);
    }

    public function login(string $username): void
    {
        $token = $this->issueToken($username);
        $this->writeCookie($token, time() + $this->ttl);
        $_COOKIE[self::COOKIE] = $token;
    }

    public function logout(): void
    {
        $this->writeCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE]);
    }

    public function issueToken(string $username, ?int $now = null): string
    {
        $now ??= time();
        $payload = $this->base64UrlEncode((string) json_encode([
            'u' => $username,
            'exp' => $now + $this->ttl,
        ]));

        return $payload . '.' . $this->sign($payload);
    }

    /**
     * @return string|null Username bila tanda tangan valid dan belum kedaluwarsa.
     */
    public function readToken(string $token, ?int $now = null): ?string
    {
        $now ??= time();
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;
        if (!hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $decodedPayload = $this->base64UrlDecode($payload);
        if ($decodedPayload === null) {
            return null;
        }

        $claims = json_decode($decodedPayload, true);
        if (!is_array($claims)) {
            return null;
        }

        $expiresAt = $claims['exp'] ?? null;
        $username = $claims['u'] ?? null;
        if (!is_int($expiresAt) || !is_string($username) || $username === '') {
            return null;
        }

        return $expiresAt >= $now ? $username : null;
    }

    private function hasCredentials(): bool
    {
        return $this->passwordHash !== '' || $this->password !== '';
    }

    private function sign(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->secret(), true));
    }

    private function secret(): string
    {
        if ($this->sessionSecret !== '') {
            return $this->sessionSecret;
        }

        // Fallback deterministik: mengganti password otomatis membatalkan sesi lama.
        return hash('sha256', 'bogana|' . $this->passwordHash . '|' . $this->password);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function writeCookie(string $value, int $expiresAt): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
