import { api } from "./client";
import type { ApiSuccessResponse, OrganizationSmtpSettings } from "./types";

export type OrganizationSmtpWritePayload = {
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

export async function fetchOrganizationSmtpSettings(): Promise<OrganizationSmtpSettings> {
  const { data } = await api.get<ApiSuccessResponse<OrganizationSmtpSettings>>("/organization/smtp-settings", {
    headers: { "X-Suppress-Server-Toast": "1" },
  });
  return data.data;
}

export async function updateOrganizationSmtpSettings(
  payload: OrganizationSmtpWritePayload,
): Promise<OrganizationSmtpSettings> {
  const { data } = await api.put<ApiSuccessResponse<OrganizationSmtpSettings>>("/organization/smtp-settings", payload);
  return data.data;
}

export async function sendOrganizationSmtpTestEmail(payload: {
  to: string;
  subject?: string;
  message?: string;
}): Promise<void> {
  await api.post<ApiSuccessResponse<null>>("/organization/smtp-settings/test", payload);
}
