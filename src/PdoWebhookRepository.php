<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use Throwable;

final readonly class PdoWebhookRepository implements WebhookRepository
{
    public function __construct(private PDO $db) {}

    public function create(string $id, string $targetUrl, string $payload): bool
    {
        $statement = $this->db->prepare(
            'INSERT INTO webhooks (id, target_url, payload, status)
                VALUES (:id, :url, :payload, :status)',
        );

        try {
            $statement->execute([
                'id' => $id,
                'url' => $targetUrl,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            return true;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return false;
            }

            throw $exception;
        }
    }

    public function find(string $id): ?Webhook
    {
        $statement = $this->db->prepare(
            'SELECT id,
                target_url,
                payload,
                attempts,
                status,
                last_error
            FROM webhooks
            WHERE id = :id',
        );

        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false
            ? null
            : new Webhook(
                $row['id'],
                $row['target_url'],
                $row['payload'],
                (int) $row['attempts'],
                $row['status'],
                $row['last_error'],
            );
    }

    /**
     * @throws \Throwable
     */
    public function claimPending(int $limit, string $workerId): array
    {
        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                'SELECT 
                    id, 
                    target_url, 
                    payload, 
                    attempts, 
                    status, 
                    last_error
                FROM webhooks
                WHERE status = :status 
                  AND next_attempt_at <= UTC_TIMESTAMP()
                ORDER BY created_at
                LIMIT :limit 
                FOR UPDATE SKIP LOCKED',
            );

            $statement->bindValue('status', 'pending');
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);

            $statement->execute();

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            $ids = array_column($rows, 'id');

            if ($ids !== []) {
                $placeholders = $ids
                        |> count(...)
                        |> (static fn($x): array => array_fill(0, $x, '?'))
                        |> (static fn($x): string => implode(',', $x));

                $claim = $this->db->prepare(
                    'UPDATE webhooks
                    SET status = ?,
                        claimed_by = ?
                    WHERE id IN (' . $placeholders . ')',
                );

                $claim->execute([
                    'processing',
                    $workerId,
                    ...$ids,
                ]);
            }

            $this->db->commit();

            return array_values(
                array_map(static fn(array $row): Webhook => new Webhook(
                    $row['id'],
                    $row['target_url'],
                    $row['payload'],
                    (int) $row['attempts'],
                    'processing',
                    $row['last_error'],
                ), $rows),
            );
        } catch (Throwable $exception) {
            $this->db->rollBack();

            throw $exception;
        }
    }

    public function markDelivered(string $id): void
    {
        $this->update($id, 'delivered', null);
    }

    public function markFailed(string $id, string $error): void
    {
        $this->update($id, 'failed', $error);
    }

    public function retry(string $id, string $error, int $delaySeconds): void
    {
        $delaySeconds = max(1, $delaySeconds);

        $statement = $this->db->prepare(
            'UPDATE webhooks
            SET status = :status,
                last_error = :error,
                attempts = attempts + 1,
                next_attempt_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL :delay_seconds SECOND),
                claimed_by = NULL
            WHERE id = :id',
        );

        $statement->execute([
            'id' => $id,
            'status' => 'pending',
            'error' => $error,
            'delay_seconds' => $delaySeconds,
        ]);
    }

    private function update(string $id, string $status, ?string $error): void
    {
        $statement = $this->db->prepare(
            'UPDATE webhooks
            SET status = :status,
                last_error = :error,
                attempts = attempts + 1,
                claimed_by = NULL,
                next_attempt_at = UTC_TIMESTAMP()
            WHERE id = :id',
        );

        $statement->execute([
            'id' => $id,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
