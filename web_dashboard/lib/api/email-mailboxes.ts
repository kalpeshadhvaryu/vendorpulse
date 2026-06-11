import axios from "axios";

import { api } from "./client";
import { getApiErrorMessage } from "./errors";
import type { ApiListResponse, ApiSuccessResponse, EmailMailbox } from "./types";

export const EMAIL_MAILBOX_DRIVERS = ["imap", "gmail_api"] as const;

export type EmailMailboxWritePayload = {
  name: string;
  driver: string;
  is_enabled?: boolean;
  connection_config: Record<string, unknown>;
};

export async function fetchEmailMailboxes(
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: EmailMailbox[]; meta?: ApiListResponse<EmailMailbox>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<EmailMailbox>>(`/email-mailboxes${qs ? `?${qs}` : ""}`);
  return { items: data.data, meta: data.meta };
}

export async function fetchEmailMailbox(id: string): Promise<EmailMailbox> {
  const { data } = await api.get<ApiSuccessResponse<EmailMailbox>>(`/email-mailboxes/${id}`);
  return data.data;
}

export async function createEmailMailbox(payload: EmailMailboxWritePayload): Promise<EmailMailbox> {
  const { data } = await api.post<ApiSuccessResponse<EmailMailbox>>("/email-mailboxes", payload);
  return data.data;
}

export async function updateEmailMailbox(
  id: string,
  payload: Partial<EmailMailboxWritePayload>,
): Promise<EmailMailbox> {
  const { data } = await api.patch<ApiSuccessResponse<EmailMailbox>>(`/email-mailboxes/${id}`, payload);
  return data.data;
}

export async function deleteEmailMailbox(id: string): Promise<void> {
  await api.delete(`/email-mailboxes/${id}`);
}

export async function testEmailMailboxConnection(
  id: string,
  organizationId?: string,
): Promise<{ ok: boolean; message: string }> {
  try {
    const { data } = await api.post<ApiSuccessResponse<{ ok: boolean }>>(
      `/email-mailboxes/${id}/test-connection`,
      {},
      organizationId ? { headers: { "X-Organization-Id": organizationId } } : undefined,
    );

    return {
      ok: Boolean(data.data?.ok),
      message: data.message,
    };
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.data) {
      const raw = error.response.data;
      if (typeof raw === "object" && raw !== null && "message" in raw && typeof raw.message === "string") {
        throw new Error(raw.message);
      }
      if (typeof raw === "string") {
        const match = raw.match(/"message"\s*:\s*"([^"]+)"/);
        if (match?.[1]) {
          throw new Error(match[1]);
        }
      }
    }

    throw new Error(getApiErrorMessage(error));
  }
}
