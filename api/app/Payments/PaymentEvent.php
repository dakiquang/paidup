<?php

namespace App\Payments;

final readonly class PaymentEvent
{
    public const SUCCEEDED = 'payment_intent.succeeded';

    public const FAILED = 'payment_intent.payment_failed';

    public const CANCELED = 'payment_intent.canceled';

    public function __construct(
        public string $type,
        public ?string $intentId = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?string $failureMessage = null,
    ) {}
}
