<?php

declare(strict_types=1);

namespace App;

final readonly class Webhook
{
    public function __construct(
        public string $id,
        public string $targetUrl,
        public string $payload,
        public int $attempts = 0,
        public string $status = 'pending',
        public ?string $lastError = null,
    ) {}

    public function nextAttempt(): self
    {
        return new self(
            $this->id,
            $this->targetUrl,
            $this->payload,
            $this->attempts + 1,
            $this->status,
            $this->lastError,
        );
    }
}
