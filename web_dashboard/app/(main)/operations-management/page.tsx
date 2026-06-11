"use client";

import { useQuery } from "@tanstack/react-query";
import { Building2, FileSpreadsheet, ReceiptText } from "lucide-react";
import { MainMenuSectionCards } from "@/components/navigation/main-menu-section-cards";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchInvoices } from "@/lib/api/invoices";
import { queryKeys } from "@/lib/api/query-keys";
import { fetchVendors } from "@/lib/api/vendors";
import { useAuthStore } from "@/stores/auth-store";

export default function OperationsManagementPage() {
  const organizationId = useAuthStore((s) => s.organizationId);
  const isGlobalAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const canLoadScopedStats = Boolean(organizationId);

  const vendorsQuery = useQuery({
    queryKey: queryKeys.vendors({ per_page: "1" }),
    queryFn: () => fetchVendors({ per_page: 1 }),
    enabled: canLoadScopedStats,
    staleTime: 60_000,
  });

  const invoicesQuery = useQuery({
    queryKey: queryKeys.invoices({ per_page: "100" }),
    queryFn: () => fetchInvoices({ per_page: 100 }),
    enabled: canLoadScopedStats,
    staleTime: 60_000,
  });

  const invoiceItems = invoicesQuery.data?.items ?? [];
  const openInvoices = invoiceItems.filter((invoice) => !invoice.paid_at && invoice.status !== "void").length;
  const loadingStats = canLoadScopedStats && (vendorsQuery.isLoading || invoicesQuery.isLoading);

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Operations &amp; Management</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Access operational tools and jump directly to management workflows.
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
                <Building2 className="h-4 w-4" />
                Vendors
              </CardDescription>
              <CardTitle className="text-2xl">{vendorsQuery.data?.meta?.total ?? 0}</CardTitle>
            </CardHeader>
          </Card>

          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <FileSpreadsheet className="h-4 w-4" />
                Invoices
              </CardDescription>
              <CardTitle className="text-2xl">{invoicesQuery.data?.meta?.total ?? 0}</CardTitle>
            </CardHeader>
          </Card>

          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription className="flex items-center gap-2">
                <ReceiptText className="h-4 w-4" />
                Open invoices
              </CardDescription>
              <CardTitle className="text-2xl">{openInvoices}</CardTitle>
            </CardHeader>
          </Card>
        </div>
        )
      ) : (
        <Card className="border-border/60 bg-muted/20">
          <CardHeader>
            <CardTitle className="text-base">Live stats unavailable</CardTitle>
            <CardDescription>Select an organization to load Operations &amp; Management live metrics.</CardDescription>
          </CardHeader>
        </Card>
      )}

      <MainMenuSectionCards
        sectionId="operations-management"
        isGlobalAdmin={isGlobalAdmin}
        statsByHref={{
          ...(isGlobalAdmin ? { "/admin": "Organizations & users" } : {}),
          "/vendors": `${vendorsQuery.data?.meta?.total ?? 0} vendors`,
          "/invoices": `${invoicesQuery.data?.meta?.total ?? 0} invoices · ${openInvoices} open`,
        }}
      />
    </div>
  );
}
