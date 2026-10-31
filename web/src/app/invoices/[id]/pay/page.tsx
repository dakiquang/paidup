import Link from "next/link";
import { PayForm } from "./PayForm";

export default async function PayPage({ params }: { params: Promise<{ id: string }> }) {
  const id = Number((await params).id);

  return (
    <main className="mx-auto w-full max-w-md px-4 py-10">
      <Link href={`/invoices/${id}`} className="text-sm text-zinc-600 underline">
        Back to invoice
      </Link>
      <h1 className="mb-6 mt-6 text-2xl font-semibold">Pay invoice</h1>
      <PayForm invoiceId={id} />
    </main>
  );
}
