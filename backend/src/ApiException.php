<?php

declare(strict_types=1);

namespace Bogana;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /**
     * @param list<array<string, mixed>> $details
     */
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly string $errorCode = 'ERROR',
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function details(): array
    {
        return $this->details;
    }
}
