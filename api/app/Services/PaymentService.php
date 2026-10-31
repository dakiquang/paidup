<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvoiceStateConflict;
use App\Exceptions\PaymentGatewayFailed;
use App\Models\Invoice;
use App\Models\Payment;
use App\Payments\PaymentEvent;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * An invoice has one live payment at a time. Asking again returns the same
     * payment, so a double tap or a retry can never open a second charge.
     */
    public function start(Invoice $invoice): Payment
    {
        $payment = DB::transaction(function () use ($invoice) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($active = $locked->payments()->where('status', PaymentStatus::Pending)->first()) {
                return $active;
            }

            if ($locked->status !== InvoiceStatus::Open) {
                throw new InvoiceStateConflict("A {$locked->status->value} invoice can't be paid.");
            }

            $locked->update(['status' => InvoiceStatus::Processing]);

            return $locked->payments()->create([
                'idempotency_key' => (string) Str::uuid(),
                'amount_cents' => $locked->amount_cents,
                'currency' => $locked->currency,
            ]);
        });

        if ($payment->client_secret) {
            return $payment;
        }

        try {
            $intent = $this->gateway->createIntent(
                $payment->amount_cents,
                $payment->currency,
                $payment->idempotency_key,
                ['invoice_id' => (string) $invoice->id, 'payment_id' => (string) $payment->id],
            );
        } catch (PaymentGatewayFailed $e) {
            $this->release($payment, 'The payment provider could not be reached.');

            throw $e;
        }

        $payment->update([
            'provider_intent_id' => $intent->id,
            'client_secret' => $intent->clientSecret,
        ]);

        return $payment;
    }

    /**
     * Cancels at the provider first. Only then can the invoice be paid again,
     * so an old payment screen can't take money alongside a new one.
     */
    public function cancel(Invoice $invoice): void
    {
        $payment = $invoice->payments()->where('status', PaymentStatus::Pending)->first();

        if (! $payment) {
            throw new InvoiceStateConflict('There is no payment in progress.');
        }

        if ($payment->provider_intent_id) {
            try {
                $this->gateway->cancelIntent($payment->provider_intent_id);
            } catch (PaymentGatewayFailed) {
                throw new InvoiceStateConflict('This payment can no longer be canceled.');
            }
        }

        $this->release($payment);
    }

    public function handle(PaymentEvent $event): void
    {
        if (! $event->intentId) {
            return;
        }

        $payment = Payment::where('provider_intent_id', $event->intentId)->first();

        if (! $payment) {
            return;
        }

        match ($event->type) {
            PaymentEvent::SUCCEEDED => $this->settle($payment, $event),
            // The intent stays open after a decline, so the customer retries the
            // same one. A new intent here is how customers get charged twice.
            PaymentEvent::FAILED => $payment->status === PaymentStatus::Pending
                ? $payment->update(['last_error' => $event->failureMessage])
                : null,
            PaymentEvent::CANCELED => $this->release($payment),
            default => null,
        };
    }

    private function settle(Payment $payment, PaymentEvent $event): void
    {
        DB::transaction(function () use ($payment, $event) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Succeeded) {
                return;
            }

            if ($event->amountCents !== $locked->amount_cents || $event->currency !== $locked->currency) {
                Log::critical('Payment amount mismatch', ['payment_id' => $locked->id]);

                return;
            }

            $locked->update(['status' => PaymentStatus::Succeeded, 'last_error' => null]);
            $locked->invoice()->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
        });
    }

    private function release(Payment $payment, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Pending) {
                return;
            }

            $locked->update(['status' => PaymentStatus::Canceled, 'last_error' => $reason]);
            $locked->invoice()->where('status', InvoiceStatus::Processing)->update(['status' => InvoiceStatus::Open]);
        });
    }
}
