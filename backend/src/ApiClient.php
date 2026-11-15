<?php

declare(strict_types=1);

namespace Bogana;

use JsonException;
use RuntimeException;

final class ApiClient
{
    public function __construct(private readonly Config $config)
    {
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
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = []): array
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

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException("Tidak dapat menghubungi AI service pada {$url}");
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
            $message = $data['error']['message'] ?? "AI service mengembalikan status {$status}";
            throw new ApiException($status, (string) $message);
        }

        return $data;
    }
}