<?php

namespace App\Payments;

use App\Exceptions\InvalidWebhook;
use App\Exceptions\PaymentGatewayFailed;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGateway implements PaymentGateway
{
    public function __construct(
        private StripeClient $client,
        private string $webhookSecret,
    ) {}

    public function createIntent(int $amountCents, string $currency, string $idempotencyKey, array $metadata): PaymentIntent
    {
        try {
            $intent = $this->client->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'metadata' => $metadata,
                'automatic_payment_methods' => ['enabled' => true],
            ], ['idempotency_key' => $idempotencyKey]);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayFailed($e->getMessage(), previous: $e);
        }

        return new PaymentIntent($intent->id, $intent->client_secret);
    }

    public function cancelIntent(string $intentId): void
    {
        try {
            $this->client->paymentIntents->cancel($intentId);
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayFailed($e->getMessage(), previous: $e);
        }
    }

    public function parseWebhook(string $payload, string $signature): PaymentEvent
    {
        // An empty secret would let anyone sign a forged event.
        if ($this->webhookSecret === '') {
            throw new InvalidWebhook('Webhook secret is not configured.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidWebhook($e->getMessage(), previous: $e);
        }

        $intent = $event->data->object;

        if ($intent->object !== 'payment_intent') {
            return new PaymentEvent($event->type);
        }

        return new PaymentEvent(
            type: $event->type,
            intentId: $intent->id,
            amountCents: $intent->amount,
            currency: $intent->currency,
            failureMessage: $intent->last_payment_error?->message,
        );
    }
}
