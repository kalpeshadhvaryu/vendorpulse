import { api } from "./client";
import type {
  ApiListResponse,
  ApiSuccessResponse,
  DomainSocialAccount,
  MonitoringCheck,
  MonitoringCheckReassignResult,
  MonitoringLog,
  MonitoringLogSummary,
  MonitoringServerAnalytics,
} from "./types";

/** Values accepted by `StoreMonitoringCheckRequest` / `UpdateMonitoringCheckRequest`. */
export const MONITORING_CHECK_TYPES = [
  "uptime",
  "ssl",
  "domain",
  "http",
  "https",
  "tls",
  "whois",
  "tcp",
  "ping",
  "dns",
  "custom",
  "server",
] as const;

export type MonitoringCheckType = (typeof MONITORING_CHECK_TYPES)[number];

/** Types where Laravel requires a non-empty `endpoint`. */
export const MONITORING_CHECK_TYPES_REQUIRING_ENDPOINT: ReadonlySet<string> = new Set([
  "uptime",
  "ssl",
  "domain",
  "http",
  "https",
  "tls",
  "whois",
  "server",
]);

export type MonitoringCheckWritePayload = {
  name: string;
  type: string;
  endpoint?: string | null;
  configuration?: Record<string, unknown> | null;
  interval_seconds?: number;
  enabled?: boolean;
  vendor_id?: string | null;
};

export type ReassignMonitoringCheckPayload = {
  from_organization_id?: string;
  to_organization_id: string;
  execute?: boolean;
};

export type DomainSocialAccountCreatePayload = {
  monitoring_check_id: string;
  platform_name: string;
  social_handle_or_url: string;
  last_follower_count?: number;
};

export type DomainSocialAccountUpdatePayload = {
  platform_name?: string;
  social_handle_or_url?: string;
  last_follower_count?: number;
};

export async function fetchMonitoringChecks(
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: MonitoringCheck[]; meta?: ApiListResponse<MonitoringCheck>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<MonitoringCheck>>(
    `/monitoring-checks${qs ? `?${qs}` : ""}`,
  );
  return { items: data.data, meta: data.meta };
}

export async function fetchMonitoringCheck(id: string): Promise<MonitoringCheck> {
  const { data } = await api.get<ApiSuccessResponse<MonitoringCheck>>(`/monitoring-checks/${id}`);
  return data.data;
}

export async function fetchMonitoringLogs(
  checkId: string,
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: MonitoringLog[]; meta?: ApiListResponse<MonitoringLog>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<MonitoringLog>>(
    `/monitoring-checks/${checkId}/logs${qs ? `?${qs}` : ""}`,
  );
  return { items: data.data, meta: data.meta };
}

export async function fetchMonitoringLogSummary(
  checkId: string,
  params: Record<string, string | undefined> = {},
): Promise<MonitoringLogSummary> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, v);
  });
  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<MonitoringLogSummary>>(
    `/monitoring-checks/${checkId}/log-summary${qs ? `?${qs}` : ""}`,
    { headers: { "X-Suppress-Server-Toast": "1" } },
  );
  return data.data;
}

export async function fetchMonitoringServerAnalytics(
  checkId: string,
  params: Record<string, string | undefined> = {},
): Promise<MonitoringServerAnalytics> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, v);
  });
  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<MonitoringServerAnalytics>>(
    `/monitoring-checks/${checkId}/server-analytics${qs ? `?${qs}` : ""}`,
    { headers: { "X-Suppress-Server-Toast": "1" } },
  );
  return data.data;
}

export async function runMonitoringCheck(id: string): Promise<void> {
  await api.post(`/monitoring-checks/${id}/run`);
}

export async function createMonitoringCheck(payload: MonitoringCheckWritePayload): Promise<MonitoringCheck> {
  const { data } = await api.post<ApiSuccessResponse<MonitoringCheck>>("/monitoring-checks", payload);
  return data.data;
}

export async function updateMonitoringCheck(
  id: string,
  payload: Partial<MonitoringCheckWritePayload>,
): Promise<MonitoringCheck> {
  const { data } = await api.patch<ApiSuccessResponse<MonitoringCheck>>(`/monitoring-checks/${id}`, payload);
  return data.data;
}

export async function deleteMonitoringCheck(id: string): Promise<void> {
  await api.delete(`/monitoring-checks/${id}`);
}

export async function reassignMonitoringCheck(
  checkId: string,
  payload: ReassignMonitoringCheckPayload,
): Promise<MonitoringCheckReassignResult> {
  const { data } = await api.post<ApiSuccessResponse<MonitoringCheckReassignResult>>(
    `/monitoring-checks/${checkId}/reassign`,
    payload,
  );

  return data.data;
}

export async function fetchDomainSocialAccounts(
  params: Record<string, string | number | undefined> = {},
): Promise<DomainSocialAccount[]> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });

  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<DomainSocialAccount[]>>(
    `/social-accounts${qs ? `?${qs}` : ""}`,
  );

  return data.data;
}

export async function createDomainSocialAccount(
  payload: DomainSocialAccountCreatePayload,
): Promise<DomainSocialAccount> {
  const { data } = await api.post<ApiSuccessResponse<DomainSocialAccount>>("/social-accounts", payload);

  return data.data;
}

export async function updateDomainSocialAccount(
  id: string,
  payload: DomainSocialAccountUpdatePayload,
): Promise<DomainSocialAccount> {
  const { data } = await api.patch<ApiSuccessResponse<DomainSocialAccount>>(`/social-accounts/${id}`, payload);

  return data.data;
}

export async function deleteDomainSocialAccount(id: string): Promise<void> {
  await api.delete(`/social-accounts/${id}`);
}
