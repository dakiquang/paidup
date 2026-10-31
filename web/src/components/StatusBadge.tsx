import { statusLabel } from "@/lib/format";
import type { Invoice } from "@/lib/types";

const tones: Record<Invoice["status"], string> = {
  draft: "bg-zinc-100 text-zinc-700",
  open: "bg-blue-50 text-blue-800",
  processing: "bg-amber-50 text-amber-800",
  paid: "bg-emerald-50 text-emerald-800",
  void: "bg-zinc-100 text-zinc-500",
};

export function StatusBadge({ invoice }: { invoice: Pick<Invoice, "status" | "overdue"> }) {
  const tone = invoice.overdue ? "bg-red-50 text-red-800" : tones[invoice.status];

  return <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${tone}`}>{statusLabel(invoice)}</span>;
}
