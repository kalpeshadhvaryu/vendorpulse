import { api } from "./client";
import type {
  ApiListResponse,
  ApiSuccessResponse,
  ExperienceMonitoringRun,
  ExperienceMonitoringScreenshot,
  ExperienceMonitoringTest,
  ExperienceMonitoringMetricsSummary,
  ExperienceMonitoringTechnicalReport,
} from "./types";
import type { ExperienceMonitoringBrowserType } from "@/lib/experience-monitoring/browsers";

export type ExperienceMonitoringTestPayload = {
  name: string;
  login_url: string;
  login_username: string;
  login_password?: string;
  dashboard_url: string;
  interval_seconds?: number;
  browser_type?: ExperienceMonitoringBrowserType;
  timeout_ms?: number;
  concurrent_sessions?: number | null;
  configuration?: Record<string, unknown> | null;
  thresholds?: Record<string, unknown> | null;
  enabled?: boolean;
};

export async function fetchExperienceMonitoringTests(
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: ExperienceMonitoringTest[]; meta?: ApiListResponse<ExperienceMonitoringTest>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<ExperienceMonitoringTest>>(
    `/experience-monitoring-tests${qs ? `?${qs}` : ""}`,
  );

  return { items: data.data, meta: data.meta };
}

export async function fetchExperienceMonitoringTest(id: string): Promise<ExperienceMonitoringTest> {
  const { data } = await api.get<ApiSuccessResponse<ExperienceMonitoringTest>>(`/experience-monitoring-tests/${id}`);
  return data.data;
}

export async function createExperienceMonitoringTest(
  payload: ExperienceMonitoringTestPayload,
): Promise<ExperienceMonitoringTest> {
  const { data } = await api.post<ApiSuccessResponse<ExperienceMonitoringTest>>(
    "/experience-monitoring-tests",
    payload,
  );

  return data.data;
}

export async function updateExperienceMonitoringTest(
  id: string,
  payload: Partial<ExperienceMonitoringTestPayload>,
): Promise<ExperienceMonitoringTest> {
  const { data } = await api.patch<ApiSuccessResponse<ExperienceMonitoringTest>>(
    `/experience-monitoring-tests/${id}`,
    payload,
  );

  return data.data;
}

export async function deleteExperienceMonitoringTest(id: string): Promise<void> {
  await api.delete(`/experience-monitoring-tests/${id}`);
}

export async function triggerExperienceMonitoringTest(id: string): Promise<void> {
  await api.post(`/experience-monitoring-tests/${id}/trigger`);
}

export async function fetchExperienceMonitoringRuns(
  testId: string,
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: ExperienceMonitoringRun[]; meta?: ApiListResponse<ExperienceMonitoringRun>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();

  const { data } = await api.get<ApiListResponse<ExperienceMonitoringRun>>(
    `/experience-monitoring-tests/${testId}/runs${qs ? `?${qs}` : ""}`,
  );

  return { items: data.data, meta: data.meta };
}

export async function fetchExperienceMonitoringMetrics(
  testId: string,
  params: Record<string, string | undefined> = {},
): Promise<ExperienceMonitoringMetricsSummary> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, v);
  });
  const qs = search.toString();

  const { data } = await api.get<ApiSuccessResponse<ExperienceMonitoringMetricsSummary>>(
    `/experience-monitoring-tests/${testId}/metrics${qs ? `?${qs}` : ""}`,
  );

  return data.data;
}

export async function fetchExperienceMonitoringScreenshots(
  testId: string,
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: ExperienceMonitoringScreenshot[]; meta?: ApiListResponse<ExperienceMonitoringScreenshot>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();

  const { data } = await api.get<ApiListResponse<ExperienceMonitoringScreenshot>>(
    `/experience-monitoring-tests/${testId}/screenshots${qs ? `?${qs}` : ""}`,
  );

  return { items: data.data, meta: data.meta };
}

export async function fetchExperienceMonitoringReport(
  testId: string,
  params: Record<string, string | undefined> = {},
): Promise<ExperienceMonitoringTechnicalReport> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, v);
  });
  const qs = search.toString();

  const { data } = await api.get<ApiSuccessResponse<ExperienceMonitoringTechnicalReport>>(
    `/experience-monitoring-tests/${testId}/report${qs ? `?${qs}` : ""}`,
  );

  return data.data;
}

export function screenshotFileUrl(screenshotId: string): string {
  return `/api/v1/experience-monitoring-screenshots/${screenshotId}/file`;
}
