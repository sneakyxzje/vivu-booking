import type { AxiosResponse } from "axios";

export function extractArray<T>(response: AxiosResponse): T[] {
  const body = response.data;
  if (Array.isArray(body)) return body;
  if (body?.data && Array.isArray(body.data)) return body.data;
  return [];
}

export function extractObject<T>(
  response: AxiosResponse,
): T | null {
  const body = response.data;
  if (!body || typeof body !== "object" || Array.isArray(body)) return null;

  if (
    body.data &&
    typeof body.data === "object" &&
    !Array.isArray(body.data)
  ) {
    return body.data as T;
  }

  return body as T;
}
