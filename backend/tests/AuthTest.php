<?php

declare(strict_types=1);

/**
 * Test regresi untuk kontrol akses dashboard (tanpa framework).
 *
 * Jalankan: php backend/tests/AuthTest.php
 */

require dirname(__DIR__) . '/src/Auth.php';

use Bogana\Auth;

$assertions = 0;
$failures = 0;

function check(string $label, bool $condition): void
{
    global $assertions, $failures;

    $assertions++;
    if (!$condition) {
        $failures++;
        echo "FAIL {$label}\n";
    }
}

/**
 * @param array<string, string|null> $env
 */
function withEnv(array $env, callable $callback): void
{
    $keys = [
        'DASHBOARD_ACCESS_MODE',
        'DASHBOARD_USER',
        'DASHBOARD_PASSWORD',
        'DASHBOARD_PASSWORD_HASH',
        'DASHBOARD_SESSION_SECRET',
        'DASHBOARD_SESSION_TTL',
    ];

    $previous = [];
    foreach ($keys as $key) {
        $previous[$key] = getenv($key);
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    foreach ($env as $key => $value) {
        if ($value !== null) {
            putenv("{$key}={$value}");
        }
    }

    try {
        $callback();
    } finally {
        foreach ($keys as $key) {
            putenv($key);
            if ($previous[$key] !== false) {
                putenv("{$key}={$previous[$key]}");
            }
        }
        unset($_COOKIE['bogana_auth']);
    }
}

$hash = password_hash('rahasia-sekali', PASSWORD_DEFAULT);

// --- Mode akses -----------------------------------------------------------

withEnv([], function (): void {
    $auth = Auth::fromEnv();
    check('auto tanpa kredensial menjadi open', $auth->mode() === Auth::MODE_OPEN);
    check('open tidak meminta login', !$auth->requiresLogin());
    check('open bukan misconfigured', !$auth->misconfigured());
    check('open menampilkan nama publik', $auth->displayName() === 'Publik');
});

withEnv(['DASHBOARD_PASSWORD_HASH' => $hash], function (): void {
    $auth = Auth::fromEnv();
    check('auto dengan hash menjadi required', $auth->mode() === Auth::MODE_REQUIRED);
    check('required bukan misconfigured', !$auth->misconfigured());
});

withEnv(['DASHBOARD_ACCESS_MODE' => 'open', 'DASHBOARD_PASSWORD_HASH' => $hash], function (): void {
    check('mode open eksplisit menang atas kredensial', Auth::fromEnv()->mode() === Auth::MODE_OPEN);
});

withEnv(['DASHBOARD_ACCESS_MODE' => 'open'], function (): void {
    $auth = Auth::fromEnv();
    check('mode open tidak meminta login', !$auth->requiresLogin());
    check('mode open bukan misconfigured', !$auth->misconfigured());
});

withEnv(['DASHBOARD_ACCESS_MODE' => 'required'], function (): void {
    check('required tanpa kredensial dianggap misconfigured', Auth::fromEnv()->misconfigured());
});

withEnv(['DASHBOARD_ACCESS_MODE' => 'ngawur'], function (): void {
    check('mode tidak dikenal jatuh ke auto', Auth::fromEnv()->mode() === Auth::MODE_OPEN);
});

// --- Verifikasi kredensial ------------------------------------------------

withEnv(['DASHBOARD_PASSWORD_HASH' => $hash, 'DASHBOARD_USER' => 'operator'], function (): void {
    $auth = Auth::fromEnv();
    check('login hash valid diterima', $auth->attempt('operator', 'rahasia-sekali'));
    check('login password salah ditolak', !$auth->attempt('operator', 'salah'));
    check('login username salah ditolak', !$auth->attempt('admin', 'rahasia-sekali'));
});

withEnv(['DASHBOARD_PASSWORD' => 'password-dev'], function (): void {
    $auth = Auth::fromEnv();
    check('password plaintext dibandingkan konstan', $auth->attempt('admin', 'password-dev'));
    check('password plaintext salah ditolak', !$auth->attempt('admin', 'password-dev '));
});

// --- Token sesi -----------------------------------------------------------

withEnv([
    'DASHBOARD_ACCESS_MODE' => 'required',
    'DASHBOARD_PASSWORD_HASH' => $hash,
    'DASHBOARD_SESSION_SECRET' => 'secret-uji',
    'DASHBOARD_SESSION_TTL' => '600',
], function (): void {
    $auth = Auth::fromEnv();
    $issuedAt = 1_700_000_000;
    $token = $auth->issueToken('admin', $issuedAt);

    check('token valid membaca username', $auth->readToken($token, $issuedAt + 10) === 'admin');
    check('token kedaluwarsa ditolak', $auth->readToken($token, $issuedAt + 601) === null);

    $tampered = substr($token, 0, -2) . 'aa';
    check('token yang dimodifikasi ditolak', $auth->readToken($tampered, $issuedAt + 10) === null);
    check('token tanpa tanda tangan ditolak', $auth->readToken('payload-saja', $issuedAt) === null);

    $_COOKIE['bogana_auth'] = $auth->issueToken('admin');
    check('cookie token valid dianggap terautentikasi', $auth->isAuthenticated());

    $_COOKIE['bogana_auth'] = $tampered;
    check('cookie token rusak tidak terautentikasi', !$auth->isAuthenticated());

    unset($_COOKIE['bogana_auth']);
    check('tanpa cookie tidak terautentikasi', !$auth->isAuthenticated());
});

withEnv([
    'DASHBOARD_ACCESS_MODE' => 'required',
    'DASHBOARD_PASSWORD_HASH' => $hash,
    'DASHBOARD_SESSION_SECRET' => 'secret-uji',
], function () use ($hash): void {
    $auth = Auth::fromEnv();
    $token = $auth->issueToken('admin');

    withEnv([
        'DASHBOARD_ACCESS_MODE' => 'required',
        'DASHBOARD_PASSWORD_HASH' => $hash,
        'DASHBOARD_SESSION_SECRET' => 'secret-lain',
    ], function () use ($auth, $token): void {
        $other = Auth::fromEnv();
        check('token dengan secret berbeda ditolak', $other->readToken($token) === null);
        check('token milik instance lain tetap valid', $auth->readToken($token) === 'admin');
    });
});

echo $failures === 0
    ? "PASS {$assertions} assertion\n"
    : "GAGAL {$failures} dari {$assertions} assertion\n";

exit($failures === 0 ? 0 : 1);
