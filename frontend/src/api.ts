export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]>;
  constructor(
    status: number,
    body: { message?: string; errors?: Record<string, string[]> },
  ) {
    super(body.message || "The request could not be completed.");
    this.status = status;
    this.errors = body.errors || {};
  }
}
export async function csrf() {
  const response = await fetch("/sanctum/csrf-cookie", {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new ApiError(response.status, body);
  }
}
export async function api<T = any>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = document.cookie
    .split("; ")
    .find((x) => x.startsWith("XSRF-TOKEN="))
    ?.split("=")
    .slice(1)
    .join("=");
  const response = await fetch("/api" + path, {
    ...options,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Accept-Language": document.documentElement.lang || "en",
      ...(options.body instanceof FormData
        ? {}
        : { "Content-Type": "application/json" }),
      ...(token ? { "X-XSRF-TOKEN": decodeURIComponent(token) } : {}),
      ...options.headers,
    },
  });
  const body = await response.json().catch(() => ({}));
  if (!response.ok) throw new ApiError(response.status, body);
  return body;
}
export const mutation = async (
  path: string,
  data: unknown,
  method = "POST",
  key?: string,
) => {
  await csrf();
  return api(path, {
    method,
    body: JSON.stringify(data),
    headers: key ? { "Idempotency-Key": key } : {},
  });
};
export type Localized = { en: string; ar: string };
export type Service = {
  id: number;
  name: Localized;
  description: Localized;
  category_id: number;
  price_minor: number | null;
  duration_minutes: number | null;
  bookable: boolean;
  image_url?: string;
};
export type Catalog = {
  settings: Record<string, any>;
  services: Service[];
  categories: { id: number; name: Localized }[];
  staff: { id: number; name: Localized; service_ids: number[] }[];
  gallery: any[];
  courses: any[];
  content: Record<string, Localized>;
};
