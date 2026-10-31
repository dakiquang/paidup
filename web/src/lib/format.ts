import type { Invoice, Total } from "./types";

export function formatMoney(cents: number, currency: string): string {
  return new Intl.NumberFormat("en-AU", {
    style: "currency",
    currency: currency.toUpperCase(),
  }).format(cents / 100);
}

export function formatTotals(totals: Total[]): string {
  if (totals.length === 0) return "Nothing";
  return totals.map((total) => formatMoney(total.cents, total.currency)).join(" · ");
}

export function formatDate(iso: string): string {
  return new Intl.DateTimeFormat("en-AU", {
    day: "numeric",
    month: "short",
    year: "numeric",
    timeZone: "UTC",
  }).format(new Date(iso));
}

export function statusLabel(invoice: Pick<Invoice, "status" | "overdue">): string {
  if (invoice.overdue) return "Overdue";
  return { draft: "Draft", open: "Open", processing: "Payment in progress", paid: "Paid", void: "Void" }[
    invoice.status
  ];
}
