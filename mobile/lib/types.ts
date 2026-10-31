export type InvoiceStatus = "draft" | "open" | "processing" | "paid" | "void";

export type Invoice = {
  id: number;
  number: string;
  description: string;
  amount_cents: number;
  currency: string;
  status: InvoiceStatus;
  overdue: boolean;
  due_on: string;
  customer: { id: number; name: string; email: string };
};
