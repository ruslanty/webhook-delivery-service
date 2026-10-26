<?php

declare(strict_types=1);

namespace App;

interface WebhookRepository
{
    public function create(string $id, string $targetUrl, string $payload): bool;

    public function find(string $id): ?Webhook;

    /** @return list<Webhook> */
    public function claimPending(int $limit, string $workerId): array;

    public function markDelivered(string $id): void;

    public function markFailed(string $id, string $error): void;

    public function retry(string $id, string $error, int $delaySeconds): void;
}
