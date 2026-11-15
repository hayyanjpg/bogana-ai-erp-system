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