<?php

declare(strict_types=1);

use App\HttpDeliveryClient;
use App\PdoWebhookRepository;
use App\WebhookWorker;

require dirname(__DIR__) . '/vendor/autoload.php';

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

$worker = new WebhookWorker(
    new PdoWebhookRepository($database),
    new HttpDeliveryClient()
);

while (true) {
    $processed = $worker->run();

    if ($processed === 0) {
        sleep(2);
    }
}
