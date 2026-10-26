<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\PdoWebhookRepository;
use App\WebhookService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && $_SERVER['REQUEST_URI'] === '/'
) {
    echo json_encode([
        'status' => 'ok',
    ], JSON_THROW_ON_ERROR);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && preg_match('#^/webhooks/([^/]+)$#', $_SERVER['REQUEST_URI'], $matches) === 1
) {
    $database = new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'database',
            $_ENV['DB_DATABASE'] ?? 'webhook'
        ),
        $_ENV['DB_USERNAME'] ?? 'webhook',
        $_ENV['DB_PASSWORD'] ?? 'webhook',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ],
    );

    $webhook = new PdoWebhookRepository($database)->find($matches[1]);

    if ($webhook === null) {
        http_response_code(404);

        echo json_encode([
            'error' => 'Webhook not found',
        ], JSON_THROW_ON_ERROR);

        exit;
    }

    echo json_encode([
        'id' => $webhook->id,
        'status' => $webhook->status,
        'attempts' => $webhook->attempts,
        'last_error' => $webhook->lastError,
    ], JSON_THROW_ON_ERROR);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !in_array($_SERVER['REQUEST_URI'], ['/webhooks', '/webhooks/bulk'], true)
) {
    http_response_code(404);

    echo json_encode([
        'error' => 'Not found',
    ], JSON_THROW_ON_ERROR);

    exit;
}

$input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$service = null;

$database = new PDO(
    sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? 'database',
        $_ENV['DB_DATABASE'] ?? 'webhook'
    ),
    $_ENV['DB_USERNAME'] ?? 'webhook',
    $_ENV['DB_PASSWORD'] ?? 'webhook',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],
);

$service = new WebhookService(new PdoWebhookRepository($database));

if ($_SERVER['REQUEST_URI'] === '/webhooks/bulk') {
    $items = $input['webhooks'] ?? null;

    if (!is_array($items)) {
        http_response_code(422);

        echo json_encode([
            'error' => 'Must be an array',
        ], JSON_THROW_ON_ERROR);

        exit;
    }

    $accepted = $service->acceptMany(array_map(static fn(array $item): array => [
        'id' => (string)($item['id'] ?? ''),
        'target_url' => (string)($item['target_url'] ?? ''),
        'payload' => json_encode($item['payload'] ?? null, JSON_THROW_ON_ERROR),
    ], $items));

    http_response_code(202);

    echo json_encode([
        'accepted' => $accepted,
    ], JSON_THROW_ON_ERROR);

    exit;
}

$id = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';

if ($id === ''
    || !isset($input['target_url'], $input['payload'])
) {
    http_response_code(422);

    echo json_encode([
        'error' => 'Idempotency key, target URL and payload are required',
    ], JSON_THROW_ON_ERROR);

    exit;
}

$accepted = $service->accept(
    $id,
    (string)$input['target_url'],
    json_encode($input['payload'], JSON_THROW_ON_ERROR),
);

http_response_code($accepted ? 202 : 200);

echo json_encode([
    'id' => $id,
    'status' => $accepted ? 'accepted' : 'already_exists',
], JSON_THROW_ON_ERROR);
