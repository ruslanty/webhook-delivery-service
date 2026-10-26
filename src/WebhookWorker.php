<?php

declare(strict_types=1);

namespace App;

use Throwable;

final readonly class WebhookWorker
{
    public function __construct(private WebhookRepository $repository, private DeliveryClient $client) {}

    /**
     * @throws \Random\RandomException
     */
    public function run(int $limit = 10): int
    {
        $processed = 0;
        $workerId = bin2hex(random_bytes(8));

        foreach ($this->repository->claimPending($limit, $workerId) as $webhook) {
            try {
                $this->client->deliver($webhook);

                $this->repository->markDelivered($webhook->id);
            } catch (Throwable $exception) {
                if ($webhook->attempts < 2) {
                    $this->repository->retry(
                        $webhook->id,
                        $exception->getMessage(),
                        2 ** ($webhook->attempts + 1),
                    );
                } else {
                    $this->repository->markFailed(
                        $webhook->id,
                        $exception->getMessage(),
                    );
                }
            }

            $processed++;
        }

        return $processed;
    }
}
