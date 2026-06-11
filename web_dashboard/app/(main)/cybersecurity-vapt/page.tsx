"use client";

import { useQuery } from "@tanstack/react-query";
import { Gauge, Globe, ShieldCheck } from "lucide-react";
import { MainMenuSectionCards } from "@/components/navigation/main-menu-section-cards";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { queryKeys } from "@/lib/api/query-keys";
import { fetchWebsiteSpeedtestRuns } from "@/lib/api/vapt-web-health";
import { useAuthStore } from "@/stores/auth-store";

export default function CybersecurityVaptPage() {
  const organizationId = useAuthStore((s) => s.organizationId);
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const isAllOrganizationsMode = isAdmin && organizationId === null;

  const runsQuery = useQuery({
    queryKey: queryKeys.websiteSpeedtestRuns({ limit: "50", all_organizations: isAllOrganizationsMode ? "1" : "0" }),
    queryFn: () =>
      fetchWebsiteSpeedtestRuns({
        limit: 50,
        all_organizations: isAllOrganizationsMode ? 1 : undefined,
      }),
    enabled: Boolean(organizationId) || isAdmin,
    staleTime: 60_000,
  });

  const runs = runsQuery.data ?? [];
  const loadingStats = runsQuery.isLoading;
  const successfulRuns = runs.filter((run) => run.success).length;

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Cybersecurity &amp; VAPT</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Security validation tools and web health checks from one place.
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
                <Gauge className="h-4 w-4" />
                Speedtest runs
              </CardDescription>
              <CardTitle className="text-2xl">{runs.length}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <ShieldCheck className="h-4 w-4" />
                Successful checks
              </CardDescription>
              <CardTitle className="text-2xl">{successfulRuns}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Globe className="h-4 w-4" />
                Mode
              </CardDescription>
              <CardTitle className="text-base">{isAllOrganizationsMode ? "All organizations" : "Scoped organization"}</CardTitle>
            </CardHeader>
          </Card>
        </div>
      )}

      <MainMenuSectionCards
        sectionId="cybersecurity-vapt"
        statsByHref={{
          "/vapt-web-health": "Security and reliability hub",
          "/vapt-web-health/dns-check": "DNS records, policy, and resolver diagnostics",
          "/vapt-web-health/port-checker": "TCP exposure checks with quick and custom profiles",
          "/vapt-web-health/website-speedtest": `${runs.length} recent runs · ${successfulRuns} successful`,
        }}
      />
    </div>
  );
}
