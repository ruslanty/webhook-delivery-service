# Webhook delivery service

Small PHP service for accepting webhooks, storing them in MariaDB and delivering them through a separate worker.

## Requirements

- Docker Compose

## Technology stack

- PHP 8.5
- FrankenPHP
- MariaDB 13
- Valkey 9.1
- PDO
- PHPUnit 13.4
- PHPStan 2.2
- PHP-CS-Fixer 3.95
- Rector 2.6

## Usage

Start the application:

```bash
docker compose up --build
```

Health check:

```bash
curl http://localhost:8080/
```

Expected result:

```json
{"status":"ok"}
```

Accept one webhook:

```bash
curl -X POST http://localhost:8080/webhooks \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: webhook-001' \
  -d '{"target_url":"https://webhook.example.test/hook","payload":{"event":"created"}}'
```

Expected result:

```json
{"id":"webhook-001","status":"accepted"}
```

Accept a bulk request:

```bash
curl -X POST http://localhost:8080/webhooks/bulk \
  -H 'Content-Type: application/json' \
  -d '{"webhooks":[{"id":"webhook-002","target_url":"https://webhook.example.test/1","payload":{"event":"created"}},{"id":"webhook-003","target_url":"https://webhook.example.test/2","payload":{"event":"updated"}}]}'
```

Expected result:

```json
{"accepted":2}
```

Check webhook status:

```bash
curl http://localhost:8080/webhooks/webhook-001
```

The worker claims pending records before delivery. A claimed record cannot be taken by another worker. Failed delivery is retried with a growing delay and becomes `failed` after the retry limit.

Invalid input is rejected before persistence. A bulk request is validated as a whole, so an invalid item does not create partial database records.

## Reset the local database

```bash
docker compose down -v
```

## Check stored webhooks

```bash
docker compose exec database mariadb -uwebhook -pwebhook webhook \
  -e 'SELECT id, status, attempts, target_url, created_at, last_error FROM webhooks ORDER BY created_at DESC;'
```

## Monitor the worker

```bash
docker compose logs -f worker
```

Show queue state:

```bash
docker compose exec database mariadb -uwebhook -pwebhook webhook \
  -e 'SELECT status, COUNT(*) AS total FROM webhooks GROUP BY status;'
```

## Tests and code analysis

Run the complete project check:

```bash
docker compose run --rm app composer quality
```

The command runs PHPUnit, PHPStan, PHP-CS-Fixer and Rector.

Current test coverage includes successful delivery, invalid input, bulk input, invalid bulk input and failed delivery.

## Structure

```text
src/                 application and infrastructure classes
tests/               unit tests
public/index.php     HTTP entry point
bin/worker.php       delivery worker
database/schema.sql  MariaDB schema
docker/              FrankenPHP and Caddy configuration
docker-compose.yml   local services
```
