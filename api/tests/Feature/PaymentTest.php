<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Payments\PaymentEvent;
use App\Payments\PaymentGateway;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private FakeGateway $gateway;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
        $this->invoice = Invoice::factory()->create();
        Sanctum::actingAs($this->invoice->user);
    }

    private function pay()
    {
        return $this->postJson("/api/invoices/{$this->invoice->id}/payment");
    }

    private function webhook(string $type, array $overrides = [])
    {
        $this->gateway->nextEvent = new PaymentEvent(...[
            'type' => $type,
            'intentId' => 'pi_1',
            'amountCents' => 12500,
            'currency' => 'aud',
            ...$overrides,
        ]);

        return $this->call('POST', '/api/webhooks/stripe', server: ['HTTP_STRIPE_SIGNATURE' => 'valid']);
    }

    public function test_paying_opens_one_intent_and_locks_the_invoice(): void
    {
        $this->pay()->assertOk()
            ->assertJsonPath('client_secret', 'secret_1')
            ->assertJsonPath('status', 'pending');

        $this->assertSame(InvoiceStatus::Processing, $this->invoice->fresh()->status);
        $this->assertSame(12500, $this->gateway->created[0]['amountCents']);
    }

    public function test_paying_twice_never_opens_a_second_charge(): void
    {
        $this->pay()->assertOk();
        $this->pay()->assertOk()->assertJsonPath('client_secret', 'secret_1');

        $this->assertCount(1, $this->gateway->created);
        $this->assertSame(1, Payment::count());
    }

    public function test_a_decline_keeps_the_same_intent_for_the_retry(): void
    {
        $this->pay();
        $this->webhook(PaymentEvent::FAILED, ['failureMessage' => 'Card declined'])->assertNoContent();

        $this->pay()
            ->assertJsonPath('client_secret', 'secret_1')
            ->assertJsonPath('last_error', 'Card declined');

        $this->assertCount(1, $this->gateway->created);
        $this->assertSame(InvoiceStatus::Processing, $this->invoice->fresh()->status);
    }

    public function test_success_marks_the_invoice_paid_and_repeats_are_harmless(): void
    {
        $this->pay();
        $this->webhook(PaymentEvent::SUCCEEDED)->assertNoContent();
        $paidAt = $this->invoice->fresh()->paid_at;
        $this->travel(1)->hours();
        $this->webhook(PaymentEvent::SUCCEEDED)->assertNoContent();

        $fresh = $this->invoice->fresh();
        $this->assertSame(InvoiceStatus::Paid, $fresh->status);
        $this->assertTrue($paidAt->equalTo($fresh->paid_at));
    }

    public function test_a_late_decline_or_cancel_cannot_undo_a_paid_invoice(): void
    {
        $this->pay();
        $this->webhook(PaymentEvent::SUCCEEDED);
        $this->webhook(PaymentEvent::FAILED, ['failureMessage' => 'Late']);
        $this->webhook(PaymentEvent::CANCELED);

        $this->assertSame(InvoiceStatus::Paid, $this->invoice->fresh()->status);
        $this->assertSame(PaymentStatus::Succeeded, Payment::first()->status);
        $this->assertNull(Payment::first()->last_error);
    }

    #[DataProvider('mismatches')]
    public function test_a_mismatched_amount_does_not_settle(array $overrides): void
    {
        $this->pay();
        $this->webhook(PaymentEvent::SUCCEEDED, $overrides);

        $this->assertSame(InvoiceStatus::Processing, $this->invoice->fresh()->status);
    }

    public static function mismatches(): array
    {
        return [
            'amount' => [['amountCents' => 100]],
            'currency' => [['currency' => 'usd']],
        ];
    }

    public function test_canceling_stops_the_provider_first_then_allows_a_new_payment(): void
    {
        $this->pay();
        $this->deleteJson("/api/invoices/{$this->invoice->id}/payment")->assertNoContent();

        $this->assertSame(['pi_1'], $this->gateway->canceled);
        $this->assertSame(InvoiceStatus::Open, $this->invoice->fresh()->status);

        $this->pay()->assertJsonPath('client_secret', 'secret_2');
    }

    public function test_a_refused_cancel_keeps_the_payment_live(): void
    {
        $this->pay();
        $this->gateway->refuseCancel = true;

        $this->deleteJson("/api/invoices/{$this->invoice->id}/payment")->assertConflict();

        $this->assertSame(InvoiceStatus::Processing, $this->invoice->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, Payment::first()->status);
    }

    public function test_canceling_with_nothing_in_progress_conflicts(): void
    {
        $this->deleteJson("/api/invoices/{$this->invoice->id}/payment")->assertConflict();
    }

    public function test_provider_cancel_event_reopens_the_invoice(): void
    {
        $this->pay();
        $this->webhook(PaymentEvent::CANCELED)->assertNoContent();

        $this->assertSame(InvoiceStatus::Open, $this->invoice->fresh()->status);
    }

    public function test_provider_outage_leaves_the_invoice_payable(): void
    {
        $this->gateway->down = true;
        $this->pay()->assertStatus(502);

        $this->assertSame(InvoiceStatus::Open, $this->invoice->fresh()->status);

        $this->gateway->down = false;
        $this->pay()->assertOk();
    }

    #[DataProvider('unpayable')]
    public function test_only_open_invoices_can_be_paid(InvoiceStatus $status): void
    {
        $this->invoice->update(['status' => $status]);

        $this->pay()->assertConflict();
        $this->assertSame([], $this->gateway->created);
    }

    public static function unpayable(): array
    {
        return [[InvoiceStatus::Draft], [InvoiceStatus::Paid], [InvoiceStatus::Void]];
    }

    public function test_a_bad_signature_changes_nothing(): void
    {
        $this->pay();

        $this->call('POST', '/api/webhooks/stripe', server: ['HTTP_STRIPE_SIGNATURE' => 'forged'])->assertStatus(400);

        $this->assertSame(InvoiceStatus::Processing, $this->invoice->fresh()->status);
    }

    public function test_unknown_events_are_acknowledged(): void
    {
        $this->pay();

        $this->webhook(PaymentEvent::SUCCEEDED, ['intentId' => 'pi_other'])->assertNoContent();
        $this->webhook('customer.created', ['intentId' => null])->assertNoContent();
        $this->webhook('charge.updated')->assertNoContent();

        $this->assertSame(InvoiceStatus::Processing, $this->invoice->fresh()->status);
    }

    public function test_the_client_secret_is_encrypted_and_hidden(): void
    {
        $this->pay();
        $payment = Payment::first();

        $this->assertNotSame('secret_1', $payment->getRawOriginal('client_secret'));
        $this->assertArrayNotHasKey('client_secret', $payment->toArray());
    }

    public function test_another_business_cannot_touch_the_invoice(): void
    {
        Sanctum::actingAs(Customer::factory()->create()->user);

        $this->pay()->assertNotFound();
        $this->deleteJson("/api/invoices/{$this->invoice->id}/payment")->assertNotFound();
    }

    public function test_a_missing_stripe_key_is_an_outage_not_a_crash(): void
    {
        config(['services.stripe.secret' => null]);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->app->offsetUnset(PaymentGateway::class);
        (new AppServiceProvider($this->app))->register();

        $this->pay()->assertStatus(502);

        $this->assertSame(InvoiceStatus::Open, $this->invoice->fresh()->status);
    }
}
