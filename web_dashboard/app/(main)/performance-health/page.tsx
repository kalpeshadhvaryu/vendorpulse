"use client";

import { useQuery } from "@tanstack/react-query";
import { Activity, AlertTriangle, CheckCircle2 } from "lucide-react";
import { MainMenuSectionCards } from "@/components/navigation/main-menu-section-cards";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchMonitoringChecks } from "@/lib/api/monitoring";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore } from "@/stores/auth-store";

export default function PerformanceHealthPage() {
  const organizationId = useAuthStore((s) => s.organizationId);
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));

  const checksQuery = useQuery({
    queryKey: queryKeys.monitoringChecks({ per_page: "100" }),
    queryFn: () => fetchMonitoringChecks({ per_page: 100 }),
    enabled: Boolean(organizationId) || isAdmin,
    staleTime: 60_000,
  });

  const checks = checksQuery.data?.items ?? [];
  const loadingStats = checksQuery.isLoading;
  const healthyChecks = checks.filter((check) => (check.last_status ?? "").toLowerCase() === "ok").length;
  const alertingChecks = checks.filter((check) =>
    ["failed", "error", "degraded"].includes((check.last_status ?? "").toLowerCase()),
  ).length;

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Performance &amp; Health</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Monitoring and uptime workflows with quick access to dedicated tools.
        </p>
      </div>

      {loadingStats ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Skeleton className="h-28 w-full rounded-xl" />
          <Skeleton className="h-28 w-full rounded-xl" />
          <Skeleton className="h-28 w-full rounded-xl" />
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Activity className="h-4 w-4" />
                Monitoring checks
              </CardDescription>
              <CardTitle className="text-2xl">{checksQuery.data?.meta?.total ?? checks.length}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <CheckCircle2 className="h-4 w-4" />
                Healthy
              </CardDescription>
              <CardTitle className="text-2xl">{healthyChecks}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <AlertTriangle className="h-4 w-4" />
                Need attention
              </CardDescription>
              <CardTitle className="text-2xl">{alertingChecks}</CardTitle>
            </CardHeader>
          </Card>
        </div>
      )}

      <MainMenuSectionCards
        sectionId="performance-health"
        statsByHref={{
          "/monitoring": `${checksQuery.data?.meta?.total ?? checks.length} checks · ${alertingChecks} alerts`,
          "/vapt-web-health/url-checker": "Live link validation",
        }}
      />
    </div>
  );
}
