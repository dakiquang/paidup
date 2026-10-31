import { formatMoney, statusLabel } from "../lib/format";

it("formats cents with separators", () => {
  expect(formatMoney(123456, "aud")).toBe("$1,234.56");
  expect(formatMoney(5, "usd")).toBe("US$0.05");
  expect(formatMoney(1000, "eur")).toBe("€10.00");
  expect(formatMoney(1000, "gbp")).toBe("GBP 10.00");
});

it("puts overdue ahead of the status", () => {
  expect(statusLabel({ status: "open", overdue: true })).toBe("Overdue");
  expect(statusLabel({ status: "paid", overdue: false })).toBe("Paid");
});
