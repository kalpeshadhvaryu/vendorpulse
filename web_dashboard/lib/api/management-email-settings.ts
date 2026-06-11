import { api } from "./client";
import type { ApiSuccessResponse } from "./types";

export type ManagementEmailSettings = {
  notify_organization_created: boolean;
  notify_user_created: boolean;
};

export async function fetchManagementEmailSettings(): Promise<ManagementEmailSettings> {
  const { data } = await api.get<ApiSuccessResponse<ManagementEmailSettings>>(
    "/settings/management-email-notifications",
    { headers: { "X-Suppress-Server-Toast": "1" } },
  );
  return data.data;
}

export async function updateManagementEmailSettings(
  payload: Partial<ManagementEmailSettings>,
): Promise<ManagementEmailSettings> {
  const { data } = await api.put<ApiSuccessResponse<ManagementEmailSettings>>(
    "/settings/management-email-notifications",
    payload,
    { headers: { "X-Suppress-Server-Toast": "1" } },
  );
  return data.data;
}
