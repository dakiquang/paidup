"use server";

import { revalidatePath } from "next/cache";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { api, ApiError, rawRequest, TOKEN_COOKIE } from "@/lib/api";

export async function login(_: string | null, form: FormData): Promise<string | null> {
  const response = await rawRequest("/login", {
    method: "POST",
    body: JSON.stringify({
      email: form.get("email"),
      password: form.get("password"),
      device_name: "web",
    }),
  });

  if (response.status === 429) return "Too many attempts. Wait a minute and try again.";
  if (!response.ok) return "Those details don't match.";

  const { token } = await response.json();

  (await cookies()).set(TOKEN_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: 60 * 60 * 24 * 7,
  });

  redirect("/");
}

export async function logout(): Promise<void> {
  await api("/logout", { method: "POST" }).catch(() => undefined);
  (await cookies()).delete(TOKEN_COOKIE);
  redirect("/login");
}

// Server functions are public endpoints, so their arguments are untrusted at runtime.
function assertId(id: number): void {
  if (!Number.isInteger(id) || id < 1) throw new Error("Invalid invoice.");
}

export async function changeInvoice(id: number, action: "send" | "void"): Promise<void> {
  assertId(id);
  if (action !== "send" && action !== "void") throw new Error("Invalid action.");

  await api(`/invoices/${id}/${action}`, { method: "POST" });
  revalidatePath(`/invoices/${id}`);
  revalidatePath("/");
}

export async function startPayment(
  id: number,
): Promise<{ clientSecret: string; lastError: string | null } | { error: string }> {
  assertId(id);

  try {
    const payment = await api<{ client_secret: string; last_error: string | null }>(`/invoices/${id}/payment`, {
      method: "POST",
    });
    return { clientSecret: payment.client_secret, lastError: payment.last_error };
  } catch (error) {
    if (error instanceof ApiError) return { error: error.message };
    throw error;
  }
}
