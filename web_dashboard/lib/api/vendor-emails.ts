import { api } from "./client";
import type { ApiListResponse, ApiSuccessResponse, VendorEmail } from "./types";

export type CreateVendorEmailPayload = {
  vendor_id: string;
  email: string;
  label?: string | null;
  purpose?: string;
};

export async function fetchVendorEmails(params: {
  vendor_id: string;
  per_page?: number;
}): Promise<{ items: VendorEmail[]; meta?: ApiListResponse<VendorEmail>["meta"] }> {
  const search = new URLSearchParams();
  search.set("vendor_id", params.vendor_id);
  if (params.per_page !== undefined) {
    search.set("per_page", String(params.per_page));
  }
  const { data } = await api.get<ApiListResponse<VendorEmail>>(`/vendor-emails?${search.toString()}`);
  return { items: data.data, meta: data.meta };
}

export async function createVendorEmail(payload: CreateVendorEmailPayload): Promise<VendorEmail> {
  const { data } = await api.post<ApiSuccessResponse<VendorEmail>>("/vendor-emails", payload);
  return data.data;
}

export async function deleteVendorEmail(id: string): Promise<void> {
  await api.delete(`/vendor-emails/${id}`);
}
