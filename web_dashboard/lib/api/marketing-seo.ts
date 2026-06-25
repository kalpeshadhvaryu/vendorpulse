import { api } from "./client";
import type { ApiSuccessResponse, OnPageSeoAuditReport, SeoAuditMode } from "./types";

export type RunOnPageSeoAuditPayload = {
  target_url: string;
  mode?: SeoAuditMode;
  max_pages?: number;
  max_depth?: number;
  authorized?: boolean;
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
