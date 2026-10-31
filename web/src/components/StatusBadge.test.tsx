import { render, screen } from "@testing-library/react";
import { expect, it } from "vitest";
import { StatusBadge } from "./StatusBadge";

it("shows overdue in red, whatever the status", () => {
  render(<StatusBadge invoice={{ status: "open", overdue: true }} />);

  expect(screen.getByText("Overdue").className).toContain("bg-red-50");
});

it("shows paid in green", () => {
  render(<StatusBadge invoice={{ status: "paid", overdue: false }} />);

  expect(screen.getByText("Paid").className).toContain("bg-emerald-50");
});
