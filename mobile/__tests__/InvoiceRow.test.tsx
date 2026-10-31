import { fireEvent, render, screen } from "@testing-library/react-native";
import { InvoiceRow } from "../components/InvoiceRow";
import type { Invoice } from "../lib/types";

const invoice: Invoice = {
  id: 1,
  number: "INV-0001",
  description: "Retainer",
  amount_cents: 12500,
  currency: "aud",
  status: "open",
  overdue: true,
  due_on: "2026-11-01",
  customer: { id: 1, name: "Acme", email: "ap@acme.test" },
};

it("shows the amount and opens on press", async () => {
  const onPress = jest.fn();
  await render(<InvoiceRow invoice={invoice} onPress={onPress} />);

  expect(screen.getByText("$125.00")).toBeTruthy();
  await fireEvent.press(screen.getByRole("button", { name: "INV-0001, Acme, Overdue" }));
  expect(onPress).toHaveBeenCalledTimes(1);
});
