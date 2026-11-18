import { api } from "./client";
import type { ApiSuccessResponse } from "./types";

export type DnsProvider = "cloudflare" | "manual";

export type DnsDomain = {
  id: string;
  organization_id: string;
  name: string;
  provider: DnsProvider;
  cloudflare_zone_id: string | null;
  cloudflare_zone_name: string | null;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type DnsRecord = {
  id: string;
  type: string;
  name: string;
  content: string;
  ttl: number;
  priority: number | null;
  proxied: boolean | null;
  comment: string | null;
  cf_record_id: string | null;
  source: "cloudflare" | "manual";
  created_at: string | null;
  updated_at: string | null;
};

export type DnsDomainList = {
  items: DnsDomain[];
  cloudflare_configured: boolean;
  available_cloudflare_zones: Array<{ id: string; name: string; status: string | null }>;
};

export type DnsRecordList = {
  domain: DnsDomain;
  items: DnsRecord[];
};

export type DnsRecordInput = {
  type: string;
  name: string;
  content: string;
  ttl?: number;
  priority?: number | null;
  proxied?: boolean | null;
  comment?: string | null;
};

export async function fetchDnsDomains(): Promise<DnsDomainList> {
  const { data } = await api.get<ApiSuccessResponse<DnsDomainList>>("/dns/domains");
  return data.data;
}

export async function createDnsDomain(payload: {
  provider: DnsProvider;
  name?: string;
  cloudflare_zone_id?: string;
  notes?: string;
}): Promise<DnsDomain> {
  const { data } = await api.post<ApiSuccessResponse<DnsDomain>>("/dns/domains", payload);
  return data.data;
}

export async function deleteDnsDomain(id: string): Promise<void> {
  await api.delete(`/dns/domains/${id}`);
}

export async function fetchDnsRecords(domainId: string): Promise<DnsRecordList> {
  const { data } = await api.get<ApiSuccessResponse<DnsRecordList>>(
    `/dns/domains/${domainId}/records`,
  );
  return data.data;
}

export async function createDnsRecord(
  domainId: string,
  payload: DnsRecordInput,
): Promise<DnsRecord> {
  const { data } = await api.post<ApiSuccessResponse<DnsRecord>>(
    `/dns/domains/${domainId}/records`,
    payload,
  );
  return data.data;
}

export async function updateDnsRecord(
  domainId: string,
  recordId: string,
  payload: Partial<DnsRecordInput>,
): Promise<DnsRecord> {
  const { data } = await api.patch<ApiSuccessResponse<DnsRecord>>(
    `/dns/domains/${domainId}/records/${recordId}`,
    payload,
  );
  return data.data;
}

export async function deleteDnsRecord(domainId: string, recordId: string): Promise<void> {
  await api.delete(`/dns/domains/${domainId}/records/${recordId}`);
}
