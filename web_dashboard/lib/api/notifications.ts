import { api } from "./client";
import type { ApiListResponse, AppNotification } from "./types";

export async function fetchNotifications(
  params: Record<string, string | number | undefined> = {},
): Promise<{ items: AppNotification[]; meta?: ApiListResponse<AppNotification>["meta"] }> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v === undefined || v === "") return;
    search.set(k, String(v));
  });
  const qs = search.toString();
  const { data } = await api.get<ApiListResponse<AppNotification>>(
    `/notifications${qs ? `?${qs}` : ""}`,
  );
  return { items: data.data, meta: data.meta };
}

export async function markNotificationRead(id: string): Promise<void> {
  await api.patch(`/notifications/${id}/read`);
}
