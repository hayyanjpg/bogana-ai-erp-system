<?php

declare(strict_types=1);

/**
 * Test regresi untuk penentuan IP klien dari header proxy (tanpa framework).
 *
 * Jalankan: php backend/tests/ClientIpTest.php
 */

require dirname(__DIR__) . '/src/ClientIp.php';

use Bogana\ClientIp;

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

check(
    'X-Forwarded-For memakai IP terakhir dari rantai proxy',
    ClientIp::resolve(['HTTP_X_FORWARDED_FOR' => '203.0.113.7, 10.0.0.1']) === '10.0.0.1',
);

check(
    'X-Forwarded-For dengan spasi dirapikan',
    ClientIp::resolve(['HTTP_X_FORWARDED_FOR' => '  203.0.113.7  ']) === '203.0.113.7',
);

check(
    'X-Real-IP dipakai lebih dulu daripada X-Forwarded-For',
    ClientIp::resolve([
        'HTTP_X_REAL_IP' => '198.51.100.9',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
    ]) === '198.51.100.9',
);

check(
    'X-Forwarded-For dipakai bila X-Real-IP kosong',
    ClientIp::resolve([
        'HTTP_X_REAL_IP' => '',
        'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
    ]) === '198.51.100.9',
);

check(
    'REMOTE_ADDR menjadi cadangan terakhir',
    ClientIp::resolve(['REMOTE_ADDR' => '192.0.2.5']) === '192.0.2.5',
);

check(
    'IPv6 valid diterima',
    ClientIp::resolve(['REMOTE_ADDR' => '2001:db8::1']) === '2001:db8::1',
);

check(
    'nilai tidak valid dilewati',
    ClientIp::resolve([
        'HTTP_X_REAL_IP' => '999.1.1.1',
        'HTTP_X_FORWARDED_FOR' => 'bukan-ip',
        'REMOTE_ADDR' => '192.0.2.8',
    ]) === '192.0.2.8',
);

check(
    'header kosong menghasilkan string kosong',
    ClientIp::resolve([]) === '',
);

check(
    'nilai non-string diabaikan',
    ClientIp::resolve(['HTTP_X_FORWARDED_FOR' => ['203.0.113.7'], 'REMOTE_ADDR' => '192.0.2.9'])
        === '192.0.2.9',
);

echo $failures === 0
    ? "PASS {$assertions} assertion\n"
    : "GAGAL {$failures} dari {$assertions} assertion\n";

exit($failures === 0 ? 0 : 1);
