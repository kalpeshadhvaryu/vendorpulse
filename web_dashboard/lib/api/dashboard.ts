import { api } from "./client";
import type {
  ApiSuccessResponse,
  DashboardMonitoringCreateFallbackTrends,
  DashboardTrends,
} from "./types";

export async function fetchDashboardTrends(
  params: Record<string, string | number | undefined> = {},
): Promise<DashboardTrends> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });

  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<DashboardTrends>>(
    `/dashboard/trends${qs ? `?${qs}` : ""}`,
    { headers: { "X-Suppress-Server-Toast": "1" } },
  );

  return data.data;
}

export async function fetchDashboardMonitoringCreateFallbackTrends(
  params: Record<string, string | number | undefined> = {},
): Promise<DashboardMonitoringCreateFallbackTrends> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });

  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<DashboardMonitoringCreateFallbackTrends>>(
    `/dashboard/monitoring-create-fallbacks${qs ? `?${qs}` : ""}`,
    { headers: { "X-Suppress-Server-Toast": "1" } },
  );

  return data.data;
}
