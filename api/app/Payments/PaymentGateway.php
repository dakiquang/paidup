<?php

namespace App\Payments;

use App\Exceptions\InvalidWebhook;
use App\Exceptions\PaymentGatewayFailed;

interface PaymentGateway
{
    /** @throws PaymentGatewayFailed */
    public function createIntent(int $amountCents, string $currency, string $idempotencyKey, array $metadata): PaymentIntent;

    /** @throws PaymentGatewayFailed */
    public function cancelIntent(string $intentId): void;

    /** @throws InvalidWebhook */
    public function parseWebhook(string $payload, string $signature): PaymentEvent;
}
