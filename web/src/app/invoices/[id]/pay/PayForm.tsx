"use client";

import { Elements, PaymentElement, useElements, useStripe } from "@stripe/react-stripe-js";
import { loadStripe } from "@stripe/stripe-js";
import { type FormEvent, useState } from "react";
import { startPayment } from "@/app/actions";

const publishableKey = process.env.NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY;
const stripePromise = publishableKey ? loadStripe(publishableKey) : null;

const button = "rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50";

function Checkout({ invoiceId, lastError }: { invoiceId: number; lastError: string | null }) {
  const stripe = useStripe();
  const elements = useElements();
  const [error, setError] = useState(lastError);
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!stripe || !elements) return;

    setBusy(true);
    const result = await stripe.confirmPayment({
      elements,
      confirmParams: { return_url: `${window.location.origin}/invoices/${invoiceId}` },
    });
    setError(result.error?.message ?? null);
    setBusy(false);
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <PaymentElement />
      {error && (
        <p role="alert" className="text-sm text-red-700">
          {error}
        </p>
      )}
      <button disabled={!stripe || busy} className={button}>
        {busy ? "Paying…" : "Pay now"}
      </button>
    </form>
  );
}

export function PayForm({ invoiceId }: { invoiceId: number }) {
  const [payment, setPayment] = useState<{ clientSecret: string; lastError: string | null } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (!stripePromise) {
    return <p className="text-sm text-zinc-600">Add NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY to take test payments.</p>;
  }

  if (payment) {
    return (
      <Elements stripe={stripePromise} options={{ clientSecret: payment.clientSecret }}>
        <Checkout invoiceId={invoiceId} lastError={payment.lastError} />
      </Elements>
    );
  }

  async function begin() {
    setBusy(true);
    const result = await startPayment(invoiceId);
    if ("error" in result) setError(result.error);
    else setPayment(result);
    setBusy(false);
  }

  return (
    <div className="space-y-4">
      {error && (
        <p role="alert" className="text-sm text-red-700">
          {error}
        </p>
      )}
      <button onClick={begin} disabled={busy} className={button}>
        {busy ? "Preparing…" : "Continue to payment"}
      </button>
    </div>
  );
}
