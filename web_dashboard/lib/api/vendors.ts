import { api } from "./client";
import type { ApiListResponse, ApiSuccessResponse, Vendor } from "./types";

/** Body for POST/PATCH `vendors` (snake_case, matches Laravel validation). */
export type VendorWritePayload = {
  name: string;
  vendor_type: string;
  billing_email?: string | null;
  support_email?: string | null;
  website?: string | null;
  currency: string;
  expected_amount?: number | null;
  billing_cycle: string;
  renewal_date?: string | null;
  auto_detect_invoices?: boolean;
  auto_fetch_email?: boolean;
  match_inbound_from_website_domain?: boolean;
  monitoring_enabled?: boolean;
  notes?: string | null;
  status?: string;
};

export async function fetchVendor(id: string): Promise<Vendor> {
  const { data } = await api.get<ApiSuccessResponse<Vendor>>(`/vendors/${id}`);
  return data.data;
}

export async function createVendor(payload: VendorWritePayload): Promise<Vendor> {
  const { data } = await api.post<ApiSuccessResponse<Vendor>>("/vendors", payload);
  return data.data;
}

export async function updateVendor(id: string, payload: Partial<VendorWritePayload>): Promise<Vendor> {
  const { data } = await api.patch<ApiSuccessResponse<Vendor>>(`/vendors/${id}`, payload);
  return data.data;
}

export async function deleteVendor(id: string): Promise<void> {
  await api.delete(`/vendors/${id}`);
}

export async function fetchVendors(
  params: Record<string, string | number | boolean | undefined> = {},
): Promise<{ items: Vendor[]; meta?: ApiListResponse<Vendor>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    if (Array.isArray(v)) {
      v.forEach((item) => search.append(`${k}[]`, String(item)));
    } else {
      search.set(k, String(v));
    }
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<Vendor>>(
    `/vendors${qs ? `?${qs}` : ""}`,
  );
  return { items: data.data, meta: data.meta };
}
