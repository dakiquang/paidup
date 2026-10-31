import "server-only";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";

export const TOKEN_COOKIE = "paidup_token";

const baseUrl = process.env.API_URL ?? "http://127.0.0.1:8000/api";

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}

export async function rawRequest(path: string, init: RequestInit = {}, token?: string): Promise<Response> {
  return fetch(`${baseUrl}${path}`, {
    ...init,
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = (await cookies()).get(TOKEN_COOKIE)?.value;
  if (!token) redirect("/login");

  const response = await rawRequest(path, init, token);

  if (response.status === 401) redirect("/login");

  if (!response.ok) {
    const body = await response.json().catch(() => null);
    throw new ApiError(response.status, body?.message ?? "Something went wrong.");
  }

  return (response.status === 204 ? undefined : await response.json()) as T;
}
