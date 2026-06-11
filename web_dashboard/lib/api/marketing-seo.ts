import { api } from "./client";
import type { ApiSuccessResponse, OnPageSeoAuditReport } from "./types";

export type RunOnPageSeoAuditPayload = {
  target_url: string;
};

export async function runOnPageSeoAudit(
  payload: RunOnPageSeoAuditPayload,
): Promise<OnPageSeoAuditReport> {
  const { data } = await api.post<ApiSuccessResponse<OnPageSeoAuditReport>>(
    "/marketing-seo/on-page-audit",
    payload,
  );

  return data.data;
}
