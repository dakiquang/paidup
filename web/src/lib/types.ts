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
  paid_at: string | null;
  customer: { id: number; name: string; email: string };
};

export type Total = { currency: string; cents: number; count: number };

export type Summary = {
  outstanding: Total[];
  overdue: Total[];
  paid_this_month: Total[];
};
