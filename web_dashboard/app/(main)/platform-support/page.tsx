"use client";

import { useQuery } from "@tanstack/react-query";
import { Bell, Settings, Wrench } from "lucide-react";
import { MainMenuSectionCards } from "@/components/navigation/main-menu-section-cards";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchNotifications } from "@/lib/api/notifications";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore } from "@/stores/auth-store";

export default function PlatformSupportPage() {
  const organizationId = useAuthStore((s) => s.organizationId);
  const canLoadScopedStats = Boolean(organizationId);

  const notificationsQuery = useQuery({
    queryKey: queryKeys.notifications({ per_page: "50" }),
    queryFn: () => fetchNotifications({ per_page: 50 }),
    enabled: canLoadScopedStats,
    staleTime: 60_000,
  });

  const notifications = notificationsQuery.data?.items ?? [];
  const loadingStats = canLoadScopedStats && notificationsQuery.isLoading;
  const unreadNotifications = notifications.filter((item) => !item.read_at).length;

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Platform &amp; Support</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Notifications, system settings, and operational platform controls.
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
                <Bell className="h-4 w-4" />
                Recent notifications
              </CardDescription>
              <CardTitle className="text-2xl">{notifications.length}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Wrench className="h-4 w-4" />
                Unread
              </CardDescription>
              <CardTitle className="text-2xl">{unreadNotifications}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <Settings className="h-4 w-4" />
                Settings hub
              </CardDescription>
              <CardTitle className="text-base">Ready</CardTitle>
            </CardHeader>
          </Card>
        </div>
        )
      ) : (
        <Card className="border-border/60 bg-muted/20">
          <CardHeader>
            <CardTitle className="text-base">Live stats unavailable</CardTitle>
            <CardDescription>Select an organization to load Platform &amp; Support metrics.</CardDescription>
          </CardHeader>
        </Card>
      )}

      <MainMenuSectionCards
        sectionId="platform-support"
        statsByHref={{
          "/notifications": `${notifications.length} recent · ${unreadNotifications} unread`,
          "/settings": "Configuration center",
          "/docs": "Guides and references",
        }}
      />
    </div>
  );
}
