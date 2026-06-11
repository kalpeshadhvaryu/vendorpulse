import { api } from "./client";
import type { ApiListResponse, ApiSuccessResponse, EmailLog, EmailLogDetail } from "./types";

export async function fetchEmailLogs(
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: EmailLog[]; meta?: ApiListResponse<EmailLog>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<EmailLog>>(
    `/email-logs${qs ? `?${qs}` : ""}`,
  );
  return { items: data.data, meta: data.meta };
}

export async function fetchEmailLog(id: string): Promise<EmailLogDetail> {
  const { data } = await api.get<ApiSuccessResponse<EmailLogDetail>>(`/email-logs/${id}`);
  return data.data;
}
