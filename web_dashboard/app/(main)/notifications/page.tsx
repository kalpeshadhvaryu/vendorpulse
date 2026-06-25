"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { fetchNotifications, markNotificationRead } from "@/lib/api/notifications";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Skeleton } from "@/components/ui/skeleton";
import { Separator } from "@/components/ui/separator";
import { useDashboardSettings } from "@/stores/dashboard-settings-store";

function formatNotification(eventKey: string | undefined, payload: Record<string, unknown> | undefined): {
  title: string;
  message: string | null;
} {
  if (!eventKey) {
    return { title: "Notification", message: null };
  }

  if (eventKey === "vapt.website_speed_slow") {
    const target = typeof payload?.target_url === "string" ? payload.target_url : "website";
    const totalMs = payload?.total_time_ms;
    const threshold = payload?.threshold_ms;
    const totalText = typeof totalMs === "number" ? `${totalMs} ms` : "high latency";
    const thresholdText = typeof threshold === "number" ? `${threshold} ms` : "configured threshold";

    return {
      title: "Website Speed Alert",
      message: `${target} measured ${totalText}, above ${thresholdText}.`,
    };
  }

  if (eventKey === "site_monitoring.uptime_check_recovered") {
    return {
      title: "Uptime Check Recovered",
      message: "A previously failing check is healthy again.",
    };
  }

  if (eventKey === "site_monitoring.ssl_expiring_soon") {
    return {
      title: "SSL Expiring Soon",
      message: "An SSL certificate is approaching expiry.",
    };
  }

  if (eventKey === "site_monitoring.domain_expiring_soon") {
    const domain = typeof payload?.domain === "string" ? payload.domain : "domain";
    const days = payload?.domain_days_remaining;
    const daysText = typeof days === "number" ? `${days} day(s)` : "soon";

    return {
      title: "Domain Registration Expiring Soon",
      message: `${domain} expires in ${daysText}. Review the WHOIS/domain check in Monitoring.`,
    };
  }

  return {
    title: eventKey,
    message: null,
  };
}

export default function NotificationsPage() {
  const queryClient = useQueryClient();
  const notificationsPerPage = useDashboardSettings((s) => s.notificationsPerPage);
  const query = useQuery({
    queryKey: queryKeys.notifications({ per_page: String(notificationsPerPage) }),
    queryFn: () => fetchNotifications({ per_page: notificationsPerPage }),
  });

  const readMutation = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: () => {
      toast.success("Marked as read");
      void queryClient.invalidateQueries({ queryKey: ["notifications"] });
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Notifications</h1>
        <p className="mt-1 text-sm text-muted-foreground">Database notifications for your signed-in user.</p>
      </div>
      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Inbox</CardTitle>
          <CardDescription>Queued in-app events from VendorPulse services.</CardDescription>
        </CardHeader>
        <CardContent>
          {query.isLoading ? (
            <Skeleton className="h-96 w-full" />
          ) : query.isError ? (
            <p className="text-sm text-destructive">{getApiErrorMessage(query.error)}</p>
          ) : (
            <ScrollArea className="h-[min(70vh,560px)] pr-3">
              <div className="space-y-0">
                {query.data?.items.length === 0 ? (
                  <p className="py-8 text-center text-sm text-muted-foreground">You&apos;re all caught up.</p>
                ) : (
                  query.data?.items.map((n, idx) => {
                    const payload = n.data as { event?: string; payload?: Record<string, unknown> };
                    const rendered = formatNotification(payload.event, payload.payload);
                    return (
                      <div key={n.id}>
                        {idx > 0 ? <Separator className="my-2" /> : null}
                        <div className="flex flex-col gap-2 rounded-lg py-2 sm:flex-row sm:items-start sm:justify-between">
                          <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                              <p className="text-sm font-semibold">{rendered.title}</p>
                              {!n.read_at ? <Badge variant="warning">Unread</Badge> : <Badge variant="secondary">Read</Badge>}
                            </div>
                            {rendered.message ? <p className="text-sm text-muted-foreground">{rendered.message}</p> : null}
                            <p className="text-xs text-muted-foreground">{new Date(n.created_at).toLocaleString()}</p>
                            {payload.payload && Object.keys(payload.payload).length > 0 ? (
                              <pre className="mt-2 max-h-40 overflow-auto rounded-md bg-muted/50 p-2 text-[11px] leading-relaxed text-muted-foreground">
                                {JSON.stringify(payload.payload, null, 2)}
                              </pre>
                            ) : null}
                          </div>
                          {!n.read_at ? (
                            <Button
                              size="sm"
                              variant="outline"
                              disabled={readMutation.isPending}
                              onClick={() => readMutation.mutate(n.id)}
                            >
                              Mark read
                            </Button>
                          ) : null}
                        </div>
                      </div>
                    );
                  })
                )}
              </div>
            </ScrollArea>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
