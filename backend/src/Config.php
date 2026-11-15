<?php

declare(strict_types=1);

namespace Bogana;

final class Config
{
    public function __construct(
        private readonly string $aiServiceUrl,
        private readonly string $aiServiceToken,
        private readonly bool $authDisabled,
    ) {
    }

    public static function fromEnv(): self
    {
        $authDisabled = filter_var(
            getenv('AI_SERVICE_AUTH_DISABLED') ?: 'true',
            FILTER_VALIDATE_BOOLEAN,
        );

        return new self(
            rtrim(getenv('AI_SERVICE_URL') ?: 'http://ai_service:8000', '/'),
            (string) (getenv('AI_SERVICE_TOKEN') ?: ''),
            $authDisabled,
        );
    }

    public function aiServiceUrl(): string
    {
        return $this->aiServiceUrl;
    }

    public function aiServiceToken(): string
    {
        return $this->aiServiceToken;
    }

    public function authDisabled(): bool
    {
        return $this->authDisabled;
    }
}