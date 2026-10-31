<?php

namespace Tests\Unit;

use App\Exceptions\InvalidWebhook;
use App\Payments\PaymentEvent;
use App\Payments\StripeGateway;
use PHPUnit\Framework\TestCase;
use Stripe\StripeClient;

class StripeGatewayTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private function gateway(): StripeGateway
    {
        return new StripeGateway(new StripeClient('sk_test_placeholder'), self::SECRET);
    }

    private function signed(string $payload, ?int $at = null): string
    {
        $at ??= time();

        return "t={$at},v1=".hash_hmac('sha256', "{$at}.{$payload}", self::SECRET);
    }

    private function payload(string $type, array $object): string
    {
        return json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
    }

    public function test_a_signed_event_becomes_a_payment_event(): void
    {
        $payload = $this->payload(PaymentEvent::FAILED, [
            'id' => 'pi_1',
            'object' => 'payment_intent',
            'amount' => 12500,
            'currency' => 'aud',
            'last_payment_error' => ['message' => 'Card declined'],
        ]);

        $event = $this->gateway()->parseWebhook($payload, $this->signed($payload));

        $this->assertSame('pi_1', $event->intentId);
        $this->assertSame(12500, $event->amountCents);
        $this->assertSame('Card declined', $event->failureMessage);
    }

    public function test_events_about_other_objects_carry_no_intent(): void
    {
        $payload = $this->payload('customer.created', ['id' => 'cus_1', 'object' => 'customer']);

        $this->assertNull($this->gateway()->parseWebhook($payload, $this->signed($payload))->intentId);
    }

    public function test_a_forged_signature_is_refused(): void
    {
        $this->expectException(InvalidWebhook::class);

        $this->gateway()->parseWebhook('{}', 't=1,v1=forged');
    }

    public function test_a_replayed_old_event_is_refused(): void
    {
        $payload = $this->payload(PaymentEvent::SUCCEEDED, ['id' => 'pi_1', 'object' => 'payment_intent']);

        $this->expectException(InvalidWebhook::class);

        $this->gateway()->parseWebhook($payload, $this->signed($payload, time() - 3600));
    }

    public function test_a_valid_signature_on_bad_json_is_refused(): void
    {
        $this->expectException(InvalidWebhook::class);

        $this->gateway()->parseWebhook('not json', $this->signed('not json'));
    }

    public function test_a_missing_webhook_secret_refuses_everything(): void
    {
        $payload = $this->payload(PaymentEvent::SUCCEEDED, ['id' => 'pi_1', 'object' => 'payment_intent']);
        $at = time();
        $forged = "t={$at},v1=".hash_hmac('sha256', "{$at}.{$payload}", '');

        $this->expectException(InvalidWebhook::class);

        (new StripeGateway(new StripeClient('sk_test_placeholder'), ''))->parseWebhook($payload, $forged);
    }
}
