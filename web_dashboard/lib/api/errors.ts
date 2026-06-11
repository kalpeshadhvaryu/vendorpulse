import axios from "axios";

import { publicApiBaseUrl } from "./public-url";

export function getApiErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    if (error.code === "ERR_NETWORK" || error.message === "Network Error" || !error.response) {
      return `Cannot reach the API at ${publicApiBaseUrl}. Start Laravel (e.g. php artisan serve) or set NEXT_PUBLIC_API_URL in web_dashboard/.env.local.`;
    }
    let data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]>; success?: boolean }
      | string
      | undefined;

    if (typeof data === "string") {
      const rawBody = data;
      try {
        data = JSON.parse(rawBody) as { message?: string; errors?: Record<string, string[]> };
      } catch {
        const match = rawBody.match(/"message"\s*:\s*"((?:\\.|[^"\\])*)"/);
        if (match?.[1]) {
          return match[1].replace(/\\"/g, '"');
        }
      }
    }

    if (data && typeof data === "object" && data.errors) {
      const first = Object.values(data.errors)[0]?.[0];
      if (first) {
        if (
          typeof first === "string" &&
          first.toLowerCase().includes("credentials") &&
          publicApiBaseUrl.includes("localhost")
        ) {
          return `${first} If curl works with 127.0.0.1 but the app uses localhost, set NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1 in web_dashboard/.env.local and restart npm run dev.`;
        }
        return first;
      }
    }
    if (data && typeof data === "object" && data.message) return data.message;
    if (error.message) return error.message;
  }
  if (error instanceof Error) return error.message;
  return "Something went wrong.";
}
