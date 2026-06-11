"use client";

import { useEffect, useMemo } from "react";
import { useQueries, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { AlertTriangle, ArrowRight, CalendarClock, Radio } from "lucide-react";
import { StatCard } from "@/components/dashboard/stat-card";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Separator } from "@/components/ui/separator";
import { fetchVendors } from "@/lib/api/vendors";
import { fetchInvoices } from "@/lib/api/invoices";
import { fetchMonitoringChecks } from "@/lib/api/monitoring";
import { fetchNotifications } from "@/lib/api/notifications";
import {
  fetchDashboardMonitoringCreateFallbackTrends,
  fetchDashboardTrends,
} from "@/lib/api/dashboard";
import { queryKeys } from "@/lib/api/query-keys";
import { addDays, formatMoney, toIsoDate } from "@/lib/format";
import { useAuthStore } from "@/stores/auth-store";
import { useDashboardSettings } from "@/stores/dashboard-settings-store";
import { getApiErrorMessage } from "@/lib/api/errors";
import { fetchOrganizations } from "@/lib/api/organizations";
import { cn } from "@/lib/utils";

type MonitoringStatusKey = "ok" | "degraded" | "failed" | "error" | "skipped" | "unknown";

const monitoringStatusMeta: Record<MonitoringStatusKey, { label: string; colorClass: string }> = {
  ok: { label: "OK", colorClass: "bg-emerald-500" },
  degraded: { label: "Degraded", colorClass: "bg-amber-500" },
  failed: { label: "Failed", colorClass: "bg-red-500" },
  error: { label: "Error", colorClass: "bg-rose-500" },
  skipped: { label: "Skipped", colorClass: "bg-slate-400" },
  unknown: { label: "Unknown", colorClass: "bg-zinc-400" },
};

const monitoringStatusOrder: MonitoringStatusKey[] = ["ok", "degraded", "failed", "error", "skipped", "unknown"];

function percentOf(value: number, total: number): number {
  if (total <= 0) return 0;

  return Math.round((value / total) * 100);
}

export default function DashboardPage() {
  const queryClient = useQueryClient();
  const organizationId = useAuthStore((s) => s.organizationId);
  const setOrganizationId = useAuthStore((s) => s.setOrganizationId);
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const canQueryOrganizationScoped = Boolean(organizationId);
  const isAllOrganizationsMode = isAdmin && organizationId === null;
  const vendorsPerPage = useDashboardSettings((s) => s.vendorsPerPage);
  const invoicesPerPage = useDashboardSettings((s) => s.invoicesPerPage);
  const monitoringPerPage = useDashboardSettings((s) => s.monitoringPerPage);
  const notificationsPerPage = useDashboardSettings((s) => s.notificationsPerPage);
  const monitoringQueryPerPage = isAllOrganizationsMode ? 100 : monitoringPerPage;
  const today = useMemo(() => new Date(), []);
  const renewalFrom = toIsoDate(today);
  const renewalTo = toIsoDate(addDays(today, 30));

  const results = useQueries({
    queries: [
      {
        queryKey: queryKeys.vendors({
          renewal_from: renewalFrom,
          renewal_to: renewalTo,
          per_page: String(vendorsPerPage),
        }),
        queryFn: () =>
          fetchVendors({
            renewal_from: renewalFrom,
            renewal_to: renewalTo,
            per_page: vendorsPerPage,
          }),
        enabled: canQueryOrganizationScoped,
      },
      {
        queryKey: queryKeys.invoices({ per_page: String(invoicesPerPage) }),
        queryFn: () => fetchInvoices({ per_page: invoicesPerPage }),
        enabled: canQueryOrganizationScoped,
      },
      {
        queryKey: queryKeys.monitoringChecks({ per_page: String(monitoringQueryPerPage) }),
        queryFn: () => fetchMonitoringChecks({ per_page: monitoringQueryPerPage }),
        enabled: canQueryOrganizationScoped || isAdmin,
      },
      {
        queryKey: queryKeys.notifications({ per_page: String(notificationsPerPage) }),
        queryFn: () => fetchNotifications({ per_page: notificationsPerPage }),
        enabled: canQueryOrganizationScoped,
      },
    ],
  });

  const [renewalsQ, invoicesQ, monitoringQ, notificationsQ] = results;
  const loading = results.some((r) => r.isLoading);
  const error = results.find((r) => r.isError)?.error;

  useEffect(() => {
    if (!error || !isAdmin || !organizationId) return;

    const message = getApiErrorMessage(error).toLowerCase();
    const orgContextFailed =
      message.includes("organization") &&
      (message.includes("not found") || message.includes("do not have access") || message.includes("required"));

    if (!orgContextFailed) return;

    setOrganizationId(null);
    void queryClient.invalidateQueries();
  }, [error, isAdmin, organizationId, queryClient, setOrganizationId]);

  const renewals = renewalsQ.data?.items ?? [];
  const invoices = invoicesQ.data?.items ?? [];
  const checks = monitoringQ.data?.items ?? [];
  const notifications = notificationsQ.data?.items ?? [];

  const monitoringStatusCounts = checks.reduce<Record<MonitoringStatusKey, number>>(
    (acc, check) => {
      const raw = (check.last_status ?? "").toLowerCase();
      const key: MonitoringStatusKey =
        raw === "ok" || raw === "degraded" || raw === "failed" || raw === "error" || raw === "skipped"
          ? raw
          : "unknown";
      acc[key] += 1;

      return acc;
    },
    {
      ok: 0,
      degraded: 0,
      failed: 0,
      error: 0,
      skipped: 0,
      unknown: 0,
    },
  );

  const renewalBuckets = renewals.reduce(
    (acc, vendor) => {
      if (!vendor.renewal_date) {
        return acc;
      }

      const daysAway = Math.ceil(
        (new Date(vendor.renewal_date).getTime() - new Date(renewalFrom).getTime()) / (1000 * 60 * 60 * 24),
      );

      if (daysAway <= 7) acc.next7 += 1;
      else if (daysAway <= 14) acc.next14 += 1;
      else acc.next30 += 1;

      return acc;
    },
    { next7: 0, next14: 0, next30: 0 },
  );

  const paidInvoices = invoices.filter((inv) => !!inv.paid_at || inv.status === "paid");
  const overdueInvoices = invoices.filter((inv) => {
    if (inv.paid_at || inv.status === "void" || !inv.due_on) return false;

    return new Date(inv.due_on).getTime() < Date.now();
  });

  const unpaidInvoices = invoices.filter((inv) => !inv.paid_at && inv.status !== "void");
  const unpaidTotalCents = unpaidInvoices.reduce((sum, inv) => sum + inv.amount_cents, 0);
  const unpaidCurrency = unpaidInvoices[0]?.currency ?? "USD";

  const okChecks = monitoringStatusCounts.ok;
  const alertChecks = checks.filter((c) =>
    ["failed", "error", "degraded"].includes((c.last_status ?? "").toLowerCase()),
  ).length;

  const unread = notifications.filter((n) => !n.read_at).length;

  useQuery({
    queryKey: queryKeys.organizations({ include_trashed: true }),
    queryFn: async () => {
      const response = await fetchOrganizations({ include_trashed: true });
      return response.data;
    },
    enabled: isAdmin,
  });

  const trendsQuery = useQuery({
    queryKey: queryKeys.dashboardTrends({ days: "30" }),
    queryFn: () => fetchDashboardTrends({ days: 30 }),
    enabled: canQueryOrganizationScoped,
  });

  const monitoringCreateFallbackTrendsQuery = useQuery({
    queryKey: queryKeys.dashboardMonitoringCreateFallbackTrends({ days: "30" }),
    queryFn: () => fetchDashboardMonitoringCreateFallbackTrends({ days: 30 }),
    enabled: canQueryOrganizationScoped,
  });

  const trendMonitoring = trendsQuery.data?.monitoring ?? [];
  const trendInvoices = trendsQuery.data?.invoices ?? [];
  const fallbackSeries = monitoringCreateFallbackTrendsQuery.data?.series ?? [];
  const fallbackTotal = monitoringCreateFallbackTrendsQuery.data?.total ?? 0;
  const fallbackBySourceTotals = fallbackSeries.reduce(
    (acc, point) => {
      acc.user_default += point.by_source.user_default;
      acc.membership_first += point.by_source.membership_first;
      acc.dev_database_fallback += point.by_source.dev_database_fallback;
      acc.other += point.by_source.other;

      return acc;
    },
    {
      user_default: 0,
      membership_first: 0,
      dev_database_fallback: 0,
      other: 0,
    },
  );
  const maxDowntimeRuns = Math.max(1, ...trendMonitoring.map((p) => p.downtime_runs));
  const maxIssuedAmount = Math.max(1, ...trendInvoices.map((p) => p.issued_amount_cents));
  const maxFallbackEvents = Math.max(1, ...fallbackSeries.map((p) => p.count));
  const avgAvailability =
    trendMonitoring.length > 0
      ? Math.round(
          (trendMonitoring.reduce((sum, p) => sum + (p.availability_ratio ?? 1), 0) / trendMonitoring.length) * 1000,
        ) / 10
      : null;

  if (error) {
    return (
      <div className="mx-auto max-w-3xl rounded-lg border border-destructive/40 bg-destructive/5 p-6 text-sm">
        <div className="flex items-center gap-2 font-medium text-destructive">
          <AlertTriangle className="h-4 w-4" />
          Unable to load dashboard
        </div>
        <p className="mt-2 text-muted-foreground">{getApiErrorMessage(error)}</p>
        <p className="mt-3 text-xs text-muted-foreground">
          Confirm <code className="rounded bg-muted px-1">NEXT_PUBLIC_API_URL</code> points to your Laravel API and CORS
          allows this origin.
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-8">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Reports and monitoring</h1>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Analytics and operational health for the active organization.
        </p>
        {isAdmin && !organizationId ? (
          <p className="mt-2 rounded-md border border-border/60 bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
            Global admin mode is active. Monitoring data is shown across all organizations.
          </p>
        ) : null}
      </div>

      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-32 w-full rounded-xl" />
          ))}
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            title="Due renewals"
            description="Next 30 days"
            value={String(renewals.length)}
            hint="Vendors with renewal in range"
          />
          <StatCard
            title="Uptime status"
            description="Monitoring checks"
            value={checks.length ? `${okChecks}/${checks.length} OK` : "—"}
            hint={checks.length ? `${alertChecks} need attention` : "No checks yet"}
          />
          <StatCard title="Active alerts" value={String(unread + alertChecks)} hint="Unread notifications + failing checks" />
          <StatCard
            title="Unpaid invoices"
            value={unpaidInvoices.length ? formatMoney(unpaidTotalCents, unpaidCurrency) : "—"}
            hint={unpaidInvoices.length ? `${unpaidInvoices.length} open` : "None outstanding"}
          />
        </div>
      )}

      <Card className="border-border/60">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <div>
            <CardTitle className="text-base">30-day trend strip</CardTitle>
            <CardDescription>Downtime spikes and invoice issuance activity over time</CardDescription>
          </div>
          <Button variant="ghost" size="sm" asChild>
            <Link href="/monitoring" className="gap-1">
              Monitoring history <ArrowRight className="h-3.5 w-3.5" />
            </Link>
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          {!canQueryOrganizationScoped ? (
            <p className="text-sm text-muted-foreground">Select an organization to load trend analytics.</p>
          ) : trendsQuery.isLoading ? (
            <Skeleton className="h-28 w-full" />
          ) : trendsQuery.isError ? (
            <p className="text-sm text-destructive">{getApiErrorMessage(trendsQuery.error)}</p>
          ) : trendMonitoring.length === 0 ? (
            <p className="text-sm text-muted-foreground">No trend data yet.</p>
          ) : (
            <>
              <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded-lg border border-border/60 bg-muted/20 p-3">
                  <p className="text-xs text-muted-foreground">Monitoring availability (avg)</p>
                  <p className="text-lg font-semibold">{avgAvailability != null ? `${avgAvailability}%` : "—"}</p>
                </div>
                <div className="rounded-lg border border-border/60 bg-muted/20 p-3">
                  <p className="text-xs text-muted-foreground">Downtime events (30d)</p>
                  <p className="text-lg font-semibold">
                    {trendMonitoring.reduce((sum, p) => sum + p.downtime_runs, 0)}
                  </p>
                </div>
              </div>

              <div className="rounded-lg border border-border/60 bg-muted/20 p-3">
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                  <span>Create context fallbacks (30d)</span>
                  <span>
                    {monitoringCreateFallbackTrendsQuery.isLoading
                      ? "Loading..."
                      : monitoringCreateFallbackTrendsQuery.isError
                        ? "Unavailable"
                        : `${fallbackTotal} events`}
                  </span>
                </div>
                {monitoringCreateFallbackTrendsQuery.isError ? (
                  <p className="mt-2 text-xs text-destructive">
                    {getApiErrorMessage(monitoringCreateFallbackTrendsQuery.error)}
                  </p>
                ) : fallbackSeries.length === 0 ? (
                  <p className="mt-2 text-xs text-muted-foreground">No fallback events in this window.</p>
                ) : (
                  <>
                    <div className="mt-2 flex h-12 items-end gap-[2px] rounded-md border border-border/50 bg-background/60 p-2">
                      {fallbackSeries.map((point) => (
                        <span
                          key={`f-${point.date}`}
                          className="block flex-1 rounded-sm bg-amber-500/80"
                          style={{ height: `${Math.max(8, Math.round((point.count / maxFallbackEvents) * 100))}%` }}
                          title={`${point.date}: ${point.count} create-context fallback events`}
                        />
                      ))}
                    </div>
                    <div className="mt-3 grid gap-2 sm:grid-cols-2">
                      <div className="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-1.5 text-xs">
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">User default org</span>
                          <span className="font-medium">{fallbackBySourceTotals.user_default}</span>
                        </div>
                      </div>
                      <div className="rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-1.5 text-xs">
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">First org membership</span>
                          <span className="font-medium">{fallbackBySourceTotals.membership_first}</span>
                        </div>
                      </div>
                      <div className="rounded-md border border-amber-500/35 bg-amber-500/10 px-2 py-1.5 text-xs">
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Dev DB fallback</span>
                          <span className="font-medium">{fallbackBySourceTotals.dev_database_fallback}</span>
                        </div>
                      </div>
                      <div className="rounded-md border border-zinc-500/30 bg-zinc-500/10 px-2 py-1.5 text-xs">
                        <div className="flex items-center justify-between">
                          <span className="text-muted-foreground">Other source</span>
                          <span className="font-medium">{fallbackBySourceTotals.other}</span>
                        </div>
                      </div>
                    </div>
                    <p className="mt-2 text-[11px] text-muted-foreground">
                      Source legend: events are grouped by how organization context was resolved during monitoring-check create.
                    </p>
                  </>
                )}
              </div>

              <div className="space-y-3">
                <div>
                  <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                    <span>Downtime runs by day</span>
                    <span>Higher bar = more failures/degraded/error checks</span>
                  </div>
                  <div className="flex h-14 items-end gap-[2px] rounded-md border border-border/50 bg-muted/20 p-2">
                    {trendMonitoring.map((point) => (
                      <span
                        key={`m-${point.date}`}
                        className="block flex-1 rounded-sm bg-rose-500/80"
                        style={{ height: `${Math.max(8, Math.round((point.downtime_runs / maxDowntimeRuns) * 100))}%` }}
                        title={`${point.date}: ${point.downtime_runs} downtime runs`}
                      />
                    ))}
                  </div>
                </div>

                <div>
                  <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                    <span>Issued invoice amount by day</span>
                    <span>Higher bar = more billed amount</span>
                  </div>
                  <div className="flex h-14 items-end gap-[2px] rounded-md border border-border/50 bg-muted/20 p-2">
                    {trendInvoices.map((point) => (
                      <span
                        key={`i-${point.date}`}
                        className="block flex-1 rounded-sm bg-emerald-500/80"
                        style={{ height: `${Math.max(8, Math.round((point.issued_amount_cents / maxIssuedAmount) * 100))}%` }}
                        title={`${point.date}: ${formatMoney(point.issued_amount_cents, unpaidCurrency)}`}
                      />
                    ))}
                  </div>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="border-border/60 lg:col-span-2">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <Radio className="h-4 w-4 text-primary" />
                Monitoring analytics
              </CardTitle>
              <CardDescription>Status distribution across all loaded checks</CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild>
              <Link href="/monitoring" className="gap-1">
                Monitoring <ArrowRight className="h-3.5 w-3.5" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent className="space-y-4">
            {loading ? (
              <Skeleton className="h-36 w-full" />
            ) : checks.length === 0 ? (
              <p className="text-sm text-muted-foreground">No monitoring checks configured yet.</p>
            ) : (
              <>
                <div className="h-4 w-full overflow-hidden rounded-full bg-muted">
                  {monitoringStatusOrder.map((k) => (
                    <span
                      key={k}
                      className={cn("inline-block h-full", monitoringStatusMeta[k].colorClass)}
                      style={{ width: `${percentOf(monitoringStatusCounts[k], checks.length)}%` }}
                      title={`${monitoringStatusMeta[k].label}: ${monitoringStatusCounts[k]}`}
                    />
                  ))}
                </div>
                <div className="grid gap-2 sm:grid-cols-2">
                  {monitoringStatusOrder.map((k) => (
                    <div key={k} className="rounded-md border border-border/60 bg-muted/20 p-2">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-medium">{monitoringStatusMeta[k].label}</span>
                        <span className="text-muted-foreground">
                          {monitoringStatusCounts[k]} ({percentOf(monitoringStatusCounts[k], checks.length)}%)
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
                {isAllOrganizationsMode && checks.length >= 100 ? (
                  <p className="text-xs text-muted-foreground">Showing the first 100 checks in all-organizations mode.</p>
                ) : null}
              </>
            )}
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Quick links</CardTitle>
            <CardDescription>Detailed records are in their respective menus</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2">
            <Button variant="outline" className="w-full justify-between" asChild>
              <Link href="/monitoring">
                Monitoring history <ArrowRight className="h-3.5 w-3.5" />
              </Link>
            </Button>
            <Button variant="outline" className="w-full justify-between" asChild>
              <Link href="/vendors">
                Renewals and vendors <ArrowRight className="h-3.5 w-3.5" />
              </Link>
            </Button>
            <Button variant="outline" className="w-full justify-between" asChild>
              <Link href="/invoices">
                Invoice ledger <ArrowRight className="h-3.5 w-3.5" />
              </Link>
            </Button>
            <Button variant="outline" className="w-full justify-between" asChild>
              <Link href="/notifications">
                Notification timeline <ArrowRight className="h-3.5 w-3.5" />
              </Link>
            </Button>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="border-border/60">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <div>
              <CardTitle className="flex items-center gap-2 text-base">
                <CalendarClock className="h-4 w-4 text-primary" />
                Renewal pressure (30 days)
              </CardTitle>
              <CardDescription>Visual split of upcoming renewals</CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild>
              <Link href="/vendors" className="gap-1">
                Vendors <ArrowRight className="h-3.5 w-3.5" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent className="space-y-4">
            {loading ? (
              <Skeleton className="h-32 w-full" />
            ) : renewals.length === 0 ? (
              <p className="text-sm text-muted-foreground">No renewals in the next 30 days.</p>
            ) : (
              <>
                <div className="space-y-3">
                  {[
                    { label: "0-7 days", value: renewalBuckets.next7, tone: "bg-red-500" },
                    { label: "8-14 days", value: renewalBuckets.next14, tone: "bg-amber-500" },
                    { label: "15-30 days", value: renewalBuckets.next30, tone: "bg-emerald-500" },
                  ].map((bucket) => (
                    <div key={bucket.label} className="space-y-1">
                      <div className="flex items-center justify-between text-xs">
                        <span className="font-medium">{bucket.label}</span>
                        <span className="text-muted-foreground">
                          {bucket.value} ({percentOf(bucket.value, renewals.length)}%)
                        </span>
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-muted">
                        <span className={cn("block h-full", bucket.tone)} style={{ width: `${percentOf(bucket.value, renewals.length)}%` }} />
                      </div>
                    </div>
                  ))}
                </div>
                <p className="text-xs text-muted-foreground">Total renewals tracked in window: {renewals.length}</p>
              </>
            )}
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Invoice health</CardTitle>
            <CardDescription>Collection quality and overdue exposure</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {loading ? (
              <Skeleton className="h-32 w-full" />
            ) : invoices.length === 0 ? (
              <p className="text-sm text-muted-foreground">No invoices available yet.</p>
            ) : (
              <>
                <div className="h-4 w-full overflow-hidden rounded-full bg-muted">
                  <span
                    className="inline-block h-full bg-emerald-500"
                    style={{ width: `${percentOf(paidInvoices.length, invoices.length)}%` }}
                    title="Paid"
                  />
                  <span
                    className="inline-block h-full bg-amber-500"
                    style={{ width: `${percentOf(unpaidInvoices.length, invoices.length)}%` }}
                    title="Open"
                  />
                </div>
                <div className="grid grid-cols-3 gap-2 text-center">
                  <div className="rounded-md border border-border/60 bg-muted/20 p-2">
                    <p className="text-xs text-muted-foreground">Paid</p>
                    <p className="text-base font-semibold">{paidInvoices.length}</p>
                  </div>
                  <div className="rounded-md border border-border/60 bg-muted/20 p-2">
                    <p className="text-xs text-muted-foreground">Open</p>
                    <p className="text-base font-semibold">{unpaidInvoices.length}</p>
                  </div>
                  <div className="rounded-md border border-border/60 bg-muted/20 p-2">
                    <p className="text-xs text-muted-foreground">Overdue</p>
                    <p className="text-base font-semibold">{overdueInvoices.length}</p>
                  </div>
                </div>
                <p className="text-xs text-muted-foreground">
                  Unpaid exposure: {unpaidInvoices.length ? formatMoney(unpaidTotalCents, unpaidCurrency) : "None"}
                </p>
              </>
            )}
          </CardContent>
        </Card>
      </div>

      <Separator />
      <p className="text-center text-xs text-muted-foreground">
        Organization context is sent as <code className="rounded bg-muted px-1">X-Organization-Id</code> on every API
        request.
      </p>
    </div>
  );
}
