import { api } from "./client";
import type { ApiSuccessResponse } from "./types";

export type CloudflareZone = {
  id: string;
  cf_zone_id: string;
  name: string;
  status: string | null;
  paused: boolean;
  plan_name: string | null;
  name_servers: string[];
  ssl_mode: string | null;
  ssl_status: string | null;
  last_synced_at: string | null;
  organization_id?: string | null;
  organization_name?: string | null;
  assigned_at?: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type CloudflareZoneList = {
  configured: boolean;
  allow_org_purge_cache?: boolean;
  items: CloudflareZone[];
};

export async function fetchCloudflareStatus(): Promise<{
  configured: boolean;
  allow_org_purge_cache: boolean;
}> {
  const { data } = await api.get<
    ApiSuccessResponse<{ configured: boolean; allow_org_purge_cache: boolean }>
  >("/cloudflare/status");

  return data.data;
}

export async function fetchAdminCloudflareZones(params?: {
  q?: string;
  assigned?: "0" | "1";
  organization_id?: string;
}): Promise<CloudflareZoneList> {
  const search = new URLSearchParams();
  Object.entries(params ?? {}).forEach(([k, v]) => {
    if (v) search.set(k, v);
  });
  const qs = search.toString();
  const { data } = await api.get<ApiSuccessResponse<CloudflareZoneList>>(
    `/admin/cloudflare/zones${qs ? `?${qs}` : ""}`,
  );

  return data.data;
}

export async function syncCloudflareZones(): Promise<{
  synced: number;
  created: number;
  updated: number;
}> {
  const { data } = await api.post<
    ApiSuccessResponse<{ synced: number; created: number; updated: number }>
  >("/admin/cloudflare/zones/sync");

  return data.data;
}

export async function assignCloudflareZone(
  id: string,
  organizationId: string | null,
): Promise<CloudflareZone> {
  const { data } = await api.patch<ApiSuccessResponse<CloudflareZone>>(
    `/admin/cloudflare/zones/${id}/assign`,
    { organization_id: organizationId },
  );

  return data.data;
}

export async function fetchOrgCloudflareDomains(): Promise<CloudflareZoneList> {
  const { data } = await api.get<ApiSuccessResponse<CloudflareZoneList>>("/cloudflare/domains");

  return data.data;
}

export async function refreshCloudflareDomain(id: string): Promise<CloudflareZone> {
  const { data } = await api.post<ApiSuccessResponse<CloudflareZone>>(
    `/cloudflare/domains/${id}/refresh`,
  );

  return data.data;
}

export async function purgeCloudflareDomainCache(id: string): Promise<void> {
  await api.post(`/cloudflare/domains/${id}/purge-cache`);
}
