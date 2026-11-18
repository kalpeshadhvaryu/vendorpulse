import { api } from "./client";
import type {
  ApiSuccessResponse,
  OnPageSeoAuditReport,
  SeoAuditMode,
  SeoKeyword,
  SeoKeywordCheck,
  SeoKeywordDetailResponse,
  SeoKeywordDevice,
  SeoKeywordListResponse,
  SeoKeywordPriority,
  SeoKeywordStatus,
} from "./types";

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

export type SeoKeywordPayload = {
  phrase: string;
  priority?: SeoKeywordPriority;
  target_url?: string | null;
  country?: string;
  language?: string;
  device?: SeoKeywordDevice;
  tags?: string[];
  notes?: string | null;
  status?: SeoKeywordStatus;
};

export async function fetchSeoKeywords(params?: {
  status?: string;
  priority?: string;
  q?: string;
}): Promise<SeoKeywordListResponse> {
  const search = new URLSearchParams();
  Object.entries(params ?? {}).forEach(([k, v]) => {
    if (v) search.set(k, v);
  });
  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<SeoKeywordListResponse>>(
    `/marketing-seo/keywords${qs ? `?${qs}` : ""}`,
  );

  return data.data;
}

export async function createSeoKeyword(payload: SeoKeywordPayload): Promise<SeoKeyword> {
  const { data } = await api.post<ApiSuccessResponse<SeoKeyword>>("/marketing-seo/keywords", payload);

  return data.data;
}

export async function updateSeoKeyword(
  id: string,
  payload: Partial<SeoKeywordPayload>,
): Promise<SeoKeyword> {
  const { data } = await api.patch<ApiSuccessResponse<SeoKeyword>>(
    `/marketing-seo/keywords/${id}`,
    payload,
  );

  return data.data;
}

export async function deleteSeoKeyword(id: string): Promise<void> {
  await api.delete(`/marketing-seo/keywords/${id}`);
}

export async function fetchSeoKeyword(id: string): Promise<SeoKeywordDetailResponse> {
  const { data } = await api.get<ApiSuccessResponse<SeoKeywordDetailResponse>>(
    `/marketing-seo/keywords/${id}`,
  );

  return data.data;
}

export async function checkSeoKeyword(
  id: string,
): Promise<{ keyword: SeoKeyword; check: SeoKeywordCheck; provider_configured: boolean }> {
  const { data } = await api.post<
    ApiSuccessResponse<{ keyword: SeoKeyword; check: SeoKeywordCheck; provider_configured: boolean }>
  >(`/marketing-seo/keywords/${id}/check`);

  return data.data;
}
