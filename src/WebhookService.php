<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;
use Throwable;

final readonly class WebhookService
{
    public function __construct(private WebhookRepository $repository) {}

    public function accept(string $id, string $targetUrl, string $payload): bool
    {
        $this->validate($id, $targetUrl, $payload);

        return $this->repository->create($id, $targetUrl, $payload);
    }

    /** @param list<array{id: string, target_url: string, payload: string}> $webhooks */
    public function acceptMany(array $webhooks): int
    {
        foreach ($webhooks as $webhook) {
            $this->validate(
                $webhook['id'],
                $webhook['target_url'],
                $webhook['payload'],
            );
        }

        $accepted = 0;

        foreach ($webhooks as $webhook) {
            $accepted += (int) $this->repository->create(
                $webhook['id'],
                $webhook['target_url'],
                $webhook['payload'],
            );
        }

        return $accepted;
    }

    public function deliver(string $id, DeliveryClient $client): void
    {
        $webhook = $this->repository->find($id);

        if (!$webhook instanceof Webhook) {
            throw new InvalidArgumentException('Webhook was not found');
        }

        try {
            $client->deliver($webhook);

            $this->repository->markDelivered($id);
        } catch (Throwable $exception) {
            $this->repository->markFailed($id, $exception->getMessage());

            throw $exception;
        }
    }

    private function validate(string $id, string $targetUrl, string $payload): void
    {
        if ($id === ''
            || $payload === ''
            || filter_var($targetUrl, FILTER_VALIDATE_URL) === false
        ) {
            throw new InvalidArgumentException('Webhook id, target URL and payload are required');
        }
    }
}
