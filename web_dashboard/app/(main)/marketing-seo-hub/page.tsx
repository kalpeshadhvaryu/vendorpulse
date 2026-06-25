"use client";

import { useQuery } from "@tanstack/react-query";
import { Globe, Link2, Megaphone } from "lucide-react";
import { MainMenuSectionCards } from "@/components/navigation/main-menu-section-cards";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchDomainSocialAccounts } from "@/lib/api/monitoring";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore } from "@/stores/auth-store";

export default function MarketingSeoHubPage() {
  const organizationId = useAuthStore((s) => s.organizationId);
  const canLoadScopedStats = Boolean(organizationId);

  const socialAccountsQuery = useQuery({
    queryKey: queryKeys.domainSocialAccounts("all"),
    queryFn: () => fetchDomainSocialAccounts(),
    enabled: canLoadScopedStats,
    staleTime: 60_000,
  });

  const socialAccounts = socialAccountsQuery.data ?? [];
  const loadingStats = canLoadScopedStats && socialAccountsQuery.isLoading;

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Marketing &amp; SEO</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          SEO scoring and social mapping utilities for web visibility.
        </p>
      </div>

      {canLoadScopedStats ? (
        loadingStats ? (
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
                <Megaphone className="h-4 w-4" />
                Active marketing tools
              </CardDescription>
              <CardTitle className="text-2xl">1</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Link2 className="h-4 w-4" />
                Social accounts mapped
              </CardDescription>
              <CardTitle className="text-2xl">{socialAccounts.length}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Globe className="h-4 w-4" />
                SEO scoring
              </CardDescription>
              <CardTitle className="text-base">On-page scorecard ready</CardTitle>
            </CardHeader>
          </Card>
        </div>
        )
      ) : (
        <Card className="border-border/60 bg-muted/20">
          <CardHeader>
            <CardTitle className="text-base">Live stats unavailable</CardTitle>
            <CardDescription>Select an organization to load Marketing &amp; SEO metrics.</CardDescription>
          </CardHeader>
        </Card>
      )}

      <MainMenuSectionCards
        sectionId="marketing-seo"
        statsByHref={{
          "/marketing-seo/on-page-seo-scorecard": "Quick SEO and site audits",
        }}
      />
    </div>
  );
}
