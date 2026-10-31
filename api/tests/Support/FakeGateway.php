<?php

namespace Tests\Support;

use App\Exceptions\InvalidWebhook;
use App\Exceptions\PaymentGatewayFailed;
use App\Payments\PaymentEvent;
use App\Payments\PaymentGateway;
use App\Payments\PaymentIntent;

class FakeGateway implements PaymentGateway
{
    public array $created = [];

    public array $canceled = [];

    public bool $down = false;

    public bool $refuseCancel = false;

    public ?PaymentEvent $nextEvent = null;

    public function createIntent(int $amountCents, string $currency, string $idempotencyKey, array $metadata): PaymentIntent
    {
        if ($this->down) {
            throw new PaymentGatewayFailed('down');
        }

        $this->created[] = compact('amountCents', 'currency', 'idempotencyKey', 'metadata');

        return new PaymentIntent('pi_'.count($this->created), 'secret_'.count($this->created));
    }

    public function cancelIntent(string $intentId): void
    {
        if ($this->refuseCancel) {
            throw new PaymentGatewayFailed('already succeeded');
        }

        $this->canceled[] = $intentId;
    }

    public function parseWebhook(string $payload, string $signature): PaymentEvent
    {
        if ($signature !== 'valid') {
            throw new InvalidWebhook('bad signature');
        }

        return $this->nextEvent;
    }
}
