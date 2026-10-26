<?php

declare(strict_types=1);

namespace App\Tests;

use App\DeliveryClient;
use App\Webhook;
use App\WebhookRepository;
use App\WebhookService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebhookServiceTest extends TestCase
{
    public function testSuccess(): void
    {
        $repository = $this->createMock(WebhookRepository::class);

        $repository->expects($this->once())
            ->method('create')
            ->willReturn(true);

        self::assertTrue(
            new WebhookService($repository)->accept('webhook-001', 'https://webhook.example.test/hook', '{}'),
        );
    }

    public function testInvalidWebhookFailsBeforePersistence(): void
    {
        $repository = $this->createMock(WebhookRepository::class);

        $repository->expects($this->never())->method('create');

        try {
            new WebhookService($repository)->accept('webhook-001', 'not-a-url', '{}');

            self::fail('Expected invalid webhook exception');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Webhook id, target URL and payload are required',
                $exception->getMessage(),
            );
        }
    }

    public function testBulkAcceptsValidWebhooks(): void
    {
        $repository = $this->createMock(WebhookRepository::class);

        $repository->expects($this->exactly(2))->method('create')->willReturn(true);

        self::assertSame(2, new WebhookService($repository)->acceptMany([
            ['id' => 'webhook-001', 'target_url' => 'https://webhook.example.test/1', 'payload' => '{}'],
            ['id' => 'webhook-002', 'target_url' => 'https://webhook.example.test/2', 'payload' => '{}'],
        ]));
    }

    public function testBulkRejectsInvalidWebhookWithoutPersistence(): void
    {
        $repository = $this->createMock(WebhookRepository::class);

        $repository->expects($this->never())->method('create');

        try {
            new WebhookService($repository)->acceptMany([
                ['id' => 'webhook-001', 'target_url' => 'https://webhook.example.test/1', 'payload' => '{}'],
                ['id' => 'webhook-002', 'target_url' => 'invalid', 'payload' => '{}'],
            ]);

            self::fail('Expected invalid bulk exception');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Webhook id, target URL and payload are required',
                $exception->getMessage(),
            );
        }
    }

    public function testDeliveryMarksWebhookAsDelivered(): void
    {
        $repository = $this->createMock(WebhookRepository::class);

        $repository->method('find')
            ->willReturn(new Webhook('webhook-001', 'https://webhook.example.test/hook', '{}'));

        $repository->expects($this->once())
            ->method('markDelivered')
            ->with('webhook-001');

        $client = $this->createMock(DeliveryClient::class);

        $client->expects($this->once())
            ->method('deliver');

        new WebhookService($repository)->deliver('webhook-001', $client);
    }

    public function testFailedDeliveryIsRecordedAndExceptionIsPropagated(): void
    {
        $repository = $this->createMock(WebhookRepository::class);

        $repository->method('find')
            ->willReturn(new Webhook('webhook-001', 'https://webhook.example.test/hook', '{}'));

        $repository->expects($this->once())
            ->method('markFailed')
            ->with('webhook-001', 'network error');

        $client = $this->createMock(DeliveryClient::class);

        $client->expects($this->once())
            ->method('deliver')
            ->willThrowException(new RuntimeException('network error'));

        try {
            new WebhookService($repository)->deliver('webhook-001', $client);

            self::fail('Expected delivery exception');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'network error',
                $exception->getMessage(),
            );
        }
    }
}
