<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\PdoWebhookRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PdoWebhookRepositoryIntegrationTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        if (getenv('DB_HOST') === false) {
            self::markTestSkipped('Set DB_HOST to run MariaDB integration tests');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST'),
            getenv('DB_DATABASE') ?: 'webhook',
        );

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            try {
                $this->database = new PDO(
                    $dsn,
                    getenv('DB_USERNAME') ?: 'webhook',
                    getenv('DB_PASSWORD') ?: 'webhook',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ],
                );

                break;
            } catch (PDOException $exception) {
                if ($attempt === 30) {
                    throw $exception;
                }

                usleep(1_000_000);
            }
        }

        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');

        if ($schema === false) {
            self::fail('Could not read database schema');
        }

        $this->database->exec($schema);
    }

    public function testWebhookIsStoredAndDuplicateIdIsRejected(): void
    {
        $repository = new PdoWebhookRepository($this->database);

        $id = 'integration-' . bin2hex(random_bytes(4));

        self::assertTrue($repository->create($id, 'https://webhook.example.test/hook', '{}'));
        self::assertFalse($repository->create($id, 'https://webhook.example.test/hook', '{}'));

        $stored = $repository->find($id);

        self::assertNotNull($stored);

        self::assertSame('pending', $stored->status);

        self::assertCount(1, $repository->claimPending(1, 'worker-a'));
        self::assertCount(0, $repository->claimPending(1, 'worker-b'));

        $this->database->prepare(
            'DELETE FROM webhooks
            WHERE id = :id',
        )->execute([
            'id' => $id,
        ]);
    }
}
