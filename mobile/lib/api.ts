import { clearToken, readToken } from "./session";
import type { Invoice } from "./types";

const baseUrl = process.env.EXPO_PUBLIC_API_URL ?? "http://localhost:8000/api";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = await readToken();
  const response = await fetch(`${baseUrl}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  if (response.status === 401) await clearToken();

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    throw new ApiError(response.status, body?.message ?? "Something went wrong.");
  }

  return (response.status === 204 ? undefined : await response.json()) as T;
}

export const login = (email: string, password: string) =>
  request<{ token: string }>("/login", {
    method: "POST",
    body: JSON.stringify({ email, password, device_name: "mobile" }),
  });

export const fetchInvoices = () => request<{ data: Invoice[] }>("/invoices").then((body) => body.data);

export const fetchInvoice = (id: number) => request<{ data: Invoice }>(`/invoices/${id}`).then((body) => body.data);

export const logout = () => request<void>("/logout", { method: "POST" });
