import type { Invoice } from "./types";

export function formatMoney(cents: number, currency: string): string {
  const symbol = { aud: "$", nzd: "NZ$", usd: "US$", eur: "€" }[currency] ?? `${currency.toUpperCase()} `;
  const value = (cents / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");

  return `${symbol}${value}`;
}

export function statusLabel(invoice: Pick<Invoice, "status" | "overdue">): string {
  if (invoice.overdue) return "Overdue";

  return { draft: "Draft", open: "Open", processing: "Payment in progress", paid: "Paid", void: "Void" }[
    invoice.status
  ];
}
