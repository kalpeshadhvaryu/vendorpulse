import { api } from "./client";
import type { ApiListResponse, Invoice } from "./types";

export async function fetchInvoices(
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: Invoice[]; meta?: ApiListResponse<Invoice>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<Invoice>>(
    `/invoices${qs ? `?${qs}` : ""}`,
  );
  return { items: data.data, meta: data.meta };
}
