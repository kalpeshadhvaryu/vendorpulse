import { api } from "./client";
import type { ApiSuccessResponse, MainSmtpSettings } from "./types";

export type MainSmtpWritePayload = {
  host: string;
  port: number;
  username?: string;
  password?: string;
  encryption?: "tls" | "ssl" | "none";
  from_address: string;
  from_name: string;
  reply_to_address?: string;
  reply_to_name?: string;
  timeout?: number;
  local_domain?: string;
};

export async function fetchMainSmtpSettings(): Promise<MainSmtpSettings> {
  const { data } = await api.get<ApiSuccessResponse<MainSmtpSettings>>("/settings/main-smtp", {
    headers: { "X-Suppress-Server-Toast": "1" },
  });
  return data.data;
}

export async function updateMainSmtpSettings(payload: MainSmtpWritePayload): Promise<MainSmtpSettings> {
  const { data } = await api.put<ApiSuccessResponse<MainSmtpSettings>>("/settings/main-smtp", payload, {
    headers: { "X-Suppress-Server-Toast": "1" },
  });
  return data.data;
}

export async function sendMainSmtpTestEmail(payload: {
  to: string;
  subject?: string;
  message?: string;
}): Promise<void> {
  await api.post<ApiSuccessResponse<null>>("/settings/main-smtp/test", payload, {
    headers: { "X-Suppress-Server-Toast": "1" },
  });
}
