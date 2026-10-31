import { describe, expect, it } from "vitest";
import { formatDate, formatMoney, formatTotals, statusLabel } from "./format";

describe("formatMoney", () => {
  it("formats cents without floating point drift", () => {
    expect(formatMoney(1999, "aud")).toBe("$19.99");
    expect(formatMoney(5, "aud")).toBe("$0.05");
  });

  it("names a foreign currency", () => {
    expect(formatMoney(12500, "usd")).toBe("USD 125.00".replace(" ", " "));
  });
});

describe("formatTotals", () => {
  it("joins one total per currency", () => {
    expect(
      formatTotals([
        { currency: "aud", cents: 100, count: 1 },
        { currency: "aud", cents: 250, count: 2 },
      ]),
    ).toBe("$1.00 · $2.50");
  });

  it("says nothing is there rather than showing zero in one currency", () => {
    expect(formatTotals([])).toBe("Nothing");
  });
});

describe("formatDate", () => {
  it("keeps the calendar day in any timezone", () => {
    expect(formatDate("2026-11-01")).toBe("1 Nov 2026");
  });
});

describe("statusLabel", () => {
  it("puts overdue ahead of the status", () => {
    expect(statusLabel({ status: "open", overdue: true })).toBe("Overdue");
    expect(statusLabel({ status: "processing", overdue: false })).toBe("Payment in progress");
  });
});
