import Link from "next/link";
import { notFound } from "next/navigation";
import { changeInvoice } from "@/app/actions";
import { StatusBadge } from "@/components/StatusBadge";
import { api, ApiError } from "@/lib/api";
import { formatDate, formatMoney } from "@/lib/format";
import type { Invoice } from "@/lib/types";

const button = "rounded-md px-3 py-2 text-sm font-medium";

export default async function InvoicePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  const invoice = await api<{ data: Invoice }>(`/invoices/${Number(id)}`)
    .then((response) => response.data)
    .catch((error) => {
      if (error instanceof ApiError && error.status === 404) notFound();
      throw error;
    });

  const canVoid = invoice.status === "draft" || invoice.status === "open";
  const canPay = invoice.status === "open" || invoice.status === "processing";

  return (
    <main className="mx-auto w-full max-w-xl px-4 py-10">
      <Link href="/" className="text-sm text-zinc-600 underline">
        All invoices
      </Link>

      <div className="mt-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">{invoice.number}</h1>
        <StatusBadge invoice={invoice} />
      </div>

      <p className="mt-6 text-3xl font-semibold tabular-nums">{formatMoney(invoice.amount_cents, invoice.currency)}</p>
      <p className="mt-2">{invoice.description}</p>
      <p className="text-sm text-zinc-600">
        {invoice.customer.name} · Due {formatDate(invoice.due_on)}
      </p>

      <div className="mt-8 flex gap-3">
        {canPay && (
          <Link href={`/invoices/${invoice.id}/pay`} className={`${button} bg-zinc-900 text-white`}>
            {invoice.status === "processing" ? "Continue payment" : "Pay"}
          </Link>
        )}
        {invoice.status === "draft" && (
          <form action={changeInvoice.bind(null, invoice.id, "send")}>
            <button className={`${button} bg-zinc-900 text-white`}>Send</button>
          </form>
        )}
        {canVoid && (
          <form action={changeInvoice.bind(null, invoice.id, "void")}>
            <button className={`${button} border border-zinc-300`}>Void</button>
          </form>
        )}
      </div>
    </main>
  );
}
