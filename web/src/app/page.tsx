import Link from "next/link";
import { logout } from "./actions";
import { StatusBadge } from "@/components/StatusBadge";
import { api } from "@/lib/api";
import { formatDate, formatMoney, formatTotals } from "@/lib/format";
import type { Invoice, Summary } from "@/lib/types";

export default async function DashboardPage() {
  const [summary, invoices] = await Promise.all([
    api<Summary>("/summary"),
    api<{ data: Invoice[] }>("/invoices"),
  ]);

  const stats = [
    ["Outstanding", summary.outstanding],
    ["Overdue", summary.overdue],
    ["Paid this month", summary.paid_this_month],
  ] as const;

  return (
    <main className="mx-auto w-full max-w-4xl px-4 py-10">
      <header className="mb-8 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">PaidUp</h1>
        <form action={logout}>
          <button className="text-sm text-zinc-600 underline">Sign out</button>
        </form>
      </header>

      <dl className="mb-10 grid gap-4 sm:grid-cols-3">
        {stats.map(([label, totals]) => (
          <div key={label} className="rounded-lg border border-zinc-200 p-4">
            <dt className="text-sm text-zinc-600">{label}</dt>
            <dd className="mt-1 text-xl font-semibold">{formatTotals(totals)}</dd>
          </div>
        ))}
      </dl>

      <ul className="divide-y divide-zinc-200 rounded-lg border border-zinc-200">
        {invoices.data.map((invoice) => (
          <li key={invoice.id}>
            <Link href={`/invoices/${invoice.id}`} className="flex items-center justify-between gap-4 p-4 hover:bg-zinc-50">
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium">
                  {invoice.number} · {invoice.customer.name}
                </p>
                <p className="text-sm text-zinc-600">Due {formatDate(invoice.due_on)}</p>
              </div>
              <div className="flex shrink-0 items-center gap-3">
                <StatusBadge invoice={invoice} />
                <span className="font-medium tabular-nums">{formatMoney(invoice.amount_cents, invoice.currency)}</span>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </main>
  );
}
