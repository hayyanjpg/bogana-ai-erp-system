<?php

declare(strict_types=1);

namespace Bogana;

use JsonException;
use RuntimeException;
use stdClass;

final class ApiClient
{
    public function __construct(private readonly Config $config)
    {
    }

    public function baseUrl(): string
    {
        return $this->config->aiServiceUrl();
    }

    /**
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = [], array $query = []): array
    {
        return $this->request('POST', $path, $query, $payload);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $url = $this->config->aiServiceUrl() . $path;
        $query = array_filter(
            $query,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Accept: application/json'];
        if (!$this->config->authDisabled()) {
            $headers[] = 'X-Internal-Token: ' . $this->config->aiServiceToken();
        }

        $body = '';
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            try {
                $body = json_encode($payload === [] ? new stdClass() : $payload, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Payload tidak dapat dikodekan sebagai JSON', 0, $exception);
            }
        }

        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => $this->config->timeoutSeconds(),
        ];
        if ($body !== '') {
            $options['content'] = $body;
        }

        $context = stream_context_create(['http' => $options]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            error_log(sprintf('Gagal menghubungi AI service: %s', $url));
            throw new RuntimeException(
                'AI service tidak dapat dihubungi. Jika baru deploy, tunggu ~1 menit lalu muat ulang halaman.'
            );
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Respons AI service bukan JSON yang valid', 0, $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Respons AI service tidak berupa objek JSON');
        }

        if ($status >= 400) {
            $error = is_array($data['error'] ?? null) ? $data['error'] : [];
            $message = $error['message'] ?? "AI service mengembalikan status {$status}";
            $code = $error['code'] ?? 'ERROR';
            $details = is_array($error['details'] ?? null) ? array_values($error['details']) : [];

            throw new ApiException($status, (string) $message, (string) $code, $details);
        }

        return $data;
    }
}
