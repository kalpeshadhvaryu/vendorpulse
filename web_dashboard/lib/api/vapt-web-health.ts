import { api } from "./client";
import type {
  ApiSuccessResponse,
  DnsCheckReport,
  PortCheckerReport,
  UrlCheckerMode,
  UrlCheckerReport,
  WebsiteSpeedtestReport,
  WebsiteSpeedtestRun,
} from "./types";

export type RunUrlCheckerPayload = {
  target_url: string;
  mode?: UrlCheckerMode;
  max_links?: number;
  max_pages?: number;
  max_depth?: number;
  max_total_links?: number;
  authorized?: boolean;
};

export async function runUrlChecker(payload: RunUrlCheckerPayload): Promise<UrlCheckerReport> {
  const { data } = await api.post<ApiSuccessResponse<UrlCheckerReport>>(
    "/vapt-web-health/url-checker",
    payload,
  );

  return data.data;
}

export type RunWebsiteSpeedtestPayload = {
  target_url: string;
  timeout_seconds?: number;
};

export type RunWebsiteSpeedtestOptions = {
  organizationId?: string;
};

export async function runWebsiteSpeedtest(
  payload: RunWebsiteSpeedtestPayload,
  options?: RunWebsiteSpeedtestOptions,
): Promise<WebsiteSpeedtestReport> {
  const { data } = await api.post<ApiSuccessResponse<WebsiteSpeedtestReport>>(
    "/vapt-web-health/website-speedtest",
    payload,
    options?.organizationId
      ? {
          headers: {
            "X-Organization-Id": options.organizationId,
          },
        }
      : undefined,
  );

  return data.data;
}

export async function fetchWebsiteSpeedtestRuns(
  params: Record<string, string | number | undefined> = {},
): Promise<WebsiteSpeedtestRun[]> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();

  const { data } = await api.get<ApiSuccessResponse<WebsiteSpeedtestRun[]>>(
    `/vapt-web-health/website-speedtest-runs${qs ? `?${qs}` : ""}`,
  );

  return data.data;
}

export type RunDnsCheckPayload = {
  target: string;
};

export async function runDnsCheck(payload: RunDnsCheckPayload): Promise<DnsCheckReport> {
  const { data } = await api.post<ApiSuccessResponse<DnsCheckReport>>(
    "/vapt-web-health/dns-check",
    payload,
  );

  return data.data;
}

export type RunPortCheckerPayload = {
  target: string;
  mode?: "quick" | "extended";
  custom_ports?: string;
  timeout_ms?: number;
};

export async function runPortChecker(payload: RunPortCheckerPayload): Promise<PortCheckerReport> {
  const { data } = await api.post<ApiSuccessResponse<PortCheckerReport>>(
    "/vapt-web-health/port-checker",
    payload,
  );

  return data.data;
}
