<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class HttpDeliveryClient implements DeliveryClient
{
    public function deliver(Webhook $webhook): void
    {
        $handle = curl_init($webhook->targetUrl);

        if ($handle === false) {
            throw new RuntimeException('Could not initialise HTTP client.');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $webhook->payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Id: ' . $webhook->id,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        curl_exec($handle);

        $error = curl_error($handle);

        if ($error !== ''
            || $status < 200
            || $status >= 300
        ) {
            throw new RuntimeException($error !== '' ? $error : 'Target returned HTTP ' . $status);
        }
    }
}
