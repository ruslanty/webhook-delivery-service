<?php

declare(strict_types=1);

namespace App;

interface DeliveryClient
{
    public function deliver(Webhook $webhook): void;
}
