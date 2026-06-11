"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useParams } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import {
  fetchMonitoringCheck,
  fetchMonitoringLogSummary,
  fetchMonitoringLogs,
  fetchMonitoringServerAnalytics,
} from "@/lib/api/monitoring";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import { addDays, formatDurationSeconds } from "@/lib/format";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";

const LOG_STATUS_OPTIONS = ["", "ok", "failed", "error", "degraded", "skipped"] as const;

function statusVariant(status: string): "success" | "warning" | "destructive" | "secondary" {
  const s = status.toLowerCase();
  if (s === "ok") return "success";
  if (s === "degraded") return "warning";
  if (s === "failed" || s === "error") return "destructive";
  return "secondary";
}

function toLocalDateTimeInputValue(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function MonitoringCheckHistoryPage() {
  const params = useParams();
  const checkId = typeof params?.checkId === "string" ? params.checkId : "";

  const defaultTo = useMemo(() => toLocalDateTimeInputValue(new Date()), []);
  const defaultFrom = useMemo(() => toLocalDateTimeInputValue(addDays(new Date(), -7)), []);

  const [fromAt, setFromAt] = useState(defaultFrom);
  const [toAt, setToAt] = useState(defaultTo);
  const [status, setStatus] = useState<string>("");
  const [search, setSearch] = useState("");
  const [downtimeOnly, setDowntimeOnly] = useState(false);
  const [changesOnly, setChangesOnly] = useState(true);
  const [page, setPage] = useState(1);

  const logParams = useMemo(() => {
    const p: Record<string, string> = {
      per_page: "30",
      page: String(page),
    };
    if (fromAt) p.from_at = fromAt;
    if (toAt) p.to_at = toAt;
    if (status) p.status = status;
    if (search) p.search = search;
    if (downtimeOnly) p.downtime_only = "1";
    return p;
  }, [fromAt, toAt, status, search, downtimeOnly, page]);

  const summaryParams = useMemo(() => {
    const p: Record<string, string> = {};
    if (fromAt) p.from_at = fromAt;
    if (toAt) p.to_at = toAt;
    return p;
  }, [fromAt, toAt]);

  const checkQuery = useQuery({
    queryKey: queryKeys.monitoringCheck(checkId),
    queryFn: () => fetchMonitoringCheck(checkId),
    enabled: Boolean(checkId),
  });

  const logsQuery = useQuery({
    queryKey: queryKeys.monitoringCheckLogs(checkId, logParams),
    queryFn: () => fetchMonitoringLogs(checkId, logParams),
    enabled: Boolean(checkId),
    placeholderData: keepPreviousData,
  });

  const summaryQuery = useQuery({
    queryKey: queryKeys.monitoringCheckLogSummary(checkId, summaryParams),
    queryFn: () => fetchMonitoringLogSummary(checkId, summaryParams),
    enabled: Boolean(checkId),
  });

  const serverAnalyticsQuery = useQuery({
    queryKey: queryKeys.monitoringServerAnalytics(checkId, summaryParams),
    queryFn: () => fetchMonitoringServerAnalytics(checkId, summaryParams),
    enabled: Boolean(checkId) && checkQuery.data?.type === "server",
  });

  function applyPreset(days: number) {
    const end = new Date();
    const start = addDays(end, -days);
    setFromAt(toLocalDateTimeInputValue(start));
    setToAt(toLocalDateTimeInputValue(end));
    setPage(1);
  }

  const meta = logsQuery.data?.meta;
  const lastPage = meta?.last_page ?? 1;
  const visibleRows = useMemo(() => {
    const rows = logsQuery.data?.items ?? [];
    if (!changesOnly || rows.length === 0) {
      return rows;
    }

    // Compare each row with the previous probe in this page window and keep only transitions.
    const changedIds = new Set<string>();
    let previousProbe: (typeof rows)[number] | null = null;

    for (let i = rows.length - 1; i >= 0; i -= 1) {
      const current = rows[i];
      if (!previousProbe) {
        changedIds.add(current.id);
      } else {
        const statusChanged = String(current.status).toLowerCase() !== String(previousProbe.status).toLowerCase();
        const httpChanged = (current.http_status ?? null) !== (previousProbe.http_status ?? null);
        const messageChanged = String(current.message ?? "") !== String(previousProbe.message ?? "");

        if (statusChanged || httpChanged || messageChanged) {
          changedIds.add(current.id);
        }
      }
      previousProbe = current;
    }

    return rows.filter((row) => changedIds.has(row.id));
  }, [logsQuery.data?.items, changesOnly]);

  const hasVisibleLogIssues = (logsQuery.data?.items ?? []).some((row) =>
    ["failed", "error", "degraded"].includes(row.status.toLowerCase()),
  );
  const hasWindowIssues =
    hasVisibleLogIssues ||
    (summaryQuery.data?.downtime_incidents ?? 0) > 0 ||
    (summaryQuery.data?.duration_seconds.degraded ?? 0) > 0;
  const failedRowCount = (logsQuery.data?.items ?? []).filter((r) =>
    ["failed", "error"].includes(r.status.toLowerCase()),
  ).length;
  const failedTabLabel = hasWindowIssues
    ? `Failed (${failedRowCount})`
    : "Failed";
  const issueTabClass =
    "border border-red-500/35 bg-red-500/10 text-red-700 data-[state=active]:bg-red-500/20 data-[state=active]:text-red-800 dark:border-red-500/50 dark:bg-red-500/20 dark:text-red-300 dark:data-[state=active]:bg-red-500/35 dark:data-[state=active]:text-red-100";

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1 px-2" asChild>
            <Link href="/monitoring">
              <ArrowLeft className="h-4 w-4" />
              Monitoring
            </Link>
          </Button>
          <h1 className="text-2xl font-semibold tracking-tight">
            {checkQuery.isLoading ? "Loading…" : checkQuery.data?.name ?? "Check"}
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Run history, filters, and time-in-state for this check (from monitoring logs).
          </p>
        </div>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="text-base">Filters</CardTitle>
          <CardDescription>
            Date-time range is precise to the minute. Summary uses up to 10k runs in the window. The API clamps the
            window so it does not start before this check existed, and treats the gap before the first probe as{" "}
            <strong className="text-foreground">ok</strong> when there is at least one run (otherwise that span is
            unknown and uptime ratio is —). Uptime ratio = time{" "}
            <strong className="text-foreground">ok</strong> vs{" "}
            <strong className="text-foreground">ok + failed + error + degraded</strong> (skipped/unknown excluded from
            ratio).
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-wrap gap-4">
            <div className="space-y-2">
              <Label htmlFor="hist-from">From</Label>
              <Input
                id="hist-from"
                type="datetime-local"
                value={fromAt}
                onChange={(e) => {
                  setFromAt(e.target.value);
                  setPage(1);
                }}
                className="w-auto"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="hist-to">To</Label>
              <Input
                id="hist-to"
                type="datetime-local"
                value={toAt}
                onChange={(e) => {
                  setToAt(e.target.value);
                  setPage(1);
                }}
                className="w-auto"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="hist-status">Status</Label>
              <select
                id="hist-status"
                className={cn(
                  "flex h-9 min-w-[160px] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm",
                  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                )}
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">All</option>
                {LOG_STATUS_OPTIONS.filter(Boolean).map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="hist-search">Search</Label>
              <Input
                id="hist-search"
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
                placeholder="Find downtime message, status, HTTP..."
                className="min-w-[260px]"
              />
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="secondary" size="sm" onClick={() => applyPreset(7)}>
              Last 7 days
            </Button>
            <Button type="button" variant="secondary" size="sm" onClick={() => applyPreset(30)}>
              Last 30 days
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => applyPreset(1)}>
              Today
            </Button>
            <Button
              type="button"
              variant={downtimeOnly ? "default" : "outline"}
              size="sm"
              onClick={() => {
                setDowntimeOnly((v) => !v);
                setPage(1);
              }}
            >
              Downtime only
            </Button>
            <Button
              type="button"
              variant={changesOnly ? "default" : "outline"}
              size="sm"
              onClick={() => {
                setChangesOnly((v) => !v);
                setPage(1);
              }}
            >
              Changes only
            </Button>
          </div>
        </CardContent>
      </Card>

      <Tabs defaultValue="summary" className="w-full">
        <TabsList>
          <TabsTrigger value="summary">
            Uptime Summary
          </TabsTrigger>
          <TabsTrigger value="logs">
            Logs
          </TabsTrigger>
          <TabsTrigger
            value="failed"
            className={hasWindowIssues ? issueTabClass : undefined}
          >
            {failedTabLabel}
          </TabsTrigger>
          <TabsTrigger
            value="server"
            disabled={checkQuery.data?.type !== "server"}
          >
            Server Analytics
          </TabsTrigger>
        </TabsList>

        <TabsContent value="logs">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle className="text-base">History</CardTitle>
              <CardDescription>Newest first. Message column includes probe details from the API.</CardDescription>
            </CardHeader>
            <CardContent>
              {changesOnly ? (
                <p className="mb-3 text-xs text-muted-foreground">
                  Showing changed rows only (current page window).
                </p>
              ) : null}
              {logsQuery.isLoading ? (
                <Skeleton className="h-64 w-full" />
              ) : logsQuery.isError ? (
                <p className="text-sm text-destructive">{getApiErrorMessage(logsQuery.error)}</p>
              ) : (
                <>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>When (local)</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>HTTP</TableHead>
                        <TableHead className="text-right">Latency</TableHead>
                        <TableHead>Message</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {visibleRows.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={5} className="text-center text-sm text-muted-foreground">
                            No log rows for this filter.
                          </TableCell>
                        </TableRow>
                      ) : (
                        visibleRows.map((row) => (
                          <TableRow key={row.id}>
                            <TableCell className="whitespace-nowrap text-sm">
                              {row.created_at ? new Date(row.created_at).toLocaleString() : "—"}
                            </TableCell>
                            <TableCell>
                              <Badge variant={statusVariant(row.status)}>{row.status}</Badge>
                            </TableCell>
                            <TableCell className="text-muted-foreground">{row.http_status ?? "—"}</TableCell>
                            <TableCell className="text-right text-muted-foreground">
                              {row.response_time_ms != null ? `${row.response_time_ms} ms` : "—"}
                            </TableCell>
                            <TableCell className="max-w-md truncate text-sm text-muted-foreground">
                              {row.message ?? "—"}
                            </TableCell>
                          </TableRow>
                        ))
                      )}
                    </TableBody>
                  </Table>
                  {lastPage > 1 ? (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                      <p className="text-xs text-muted-foreground">
                        Page {meta?.current_page ?? page} of {lastPage}
                      </p>
                      <div className="flex gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={page <= 1 || logsQuery.isFetching}
                          onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                          Previous
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={page >= lastPage || logsQuery.isFetching}
                          onClick={() => setPage((p) => p + 1)}
                        >
                          Next
                        </Button>
                      </div>
                    </div>
                  ) : null}
                </>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="summary">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle className="text-base">Uptime & downtime (window)</CardTitle>
              <CardDescription>
                Time-weighted between probe timestamps. Unknown is usually zero once runs exist; it is the full span only
                when there were no probes in the window.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {summaryQuery.isLoading ? (
                <Skeleton className="h-24 w-full" />
              ) : summaryQuery.isError ? (
                <p className="text-sm text-destructive">{getApiErrorMessage(summaryQuery.error)}</p>
              ) : summaryQuery.data ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Uptime ratio</p>
                    <p className="text-lg font-semibold">
                      {summaryQuery.data.uptime_ratio != null
                        ? `${(summaryQuery.data.uptime_ratio * 100).toFixed(1)}%`
                        : "—"}
                    </p>
                  </div>
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Time up (ok)</p>
                    <p className="text-lg font-semibold">{formatDurationSeconds(summaryQuery.data.duration_seconds.up)}</p>
                  </div>
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Time down (failed + error)</p>
                    <p className="text-lg font-semibold">{formatDurationSeconds(summaryQuery.data.duration_seconds.down)}</p>
                  </div>
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Failed/error runs (count)</p>
                    <p className="text-lg font-semibold">{summaryQuery.data.downtime_incidents}</p>
                  </div>
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Degraded time</p>
                    <p className="text-lg font-semibold">
                      {formatDurationSeconds(summaryQuery.data.duration_seconds.degraded)}
                    </p>
                  </div>
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Skipped time</p>
                    <p className="text-lg font-semibold">
                      {formatDurationSeconds(summaryQuery.data.duration_seconds.skipped)}
                    </p>
                  </div>
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Unknown (no probes in window)</p>
                    <p className="text-lg font-semibold">
                      {formatDurationSeconds(summaryQuery.data.duration_seconds.unknown)}
                    </p>
                  </div>
                  <div className="rounded-lg border border-border/50 bg-muted/20 p-3">
                    <p className="text-xs text-muted-foreground">Runs in window</p>
                    <p className="text-lg font-semibold">{summaryQuery.data.log_count_in_window}</p>
                  </div>
                </div>
              ) : null}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="failed">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle className="text-base">Failed &amp; error runs</CardTitle>
              <CardDescription>
                Only <strong className="text-foreground">failed</strong> and{" "}
                <strong className="text-foreground">error</strong> log entries in the selected window, newest first.
              </CardDescription>
            </CardHeader>
            <CardContent>
              {changesOnly ? (
                <p className="mb-3 text-xs text-muted-foreground">
                  Showing changed rows only (current page window).
                </p>
              ) : null}
              {logsQuery.isLoading ? (
                <Skeleton className="h-64 w-full" />
              ) : logsQuery.isError ? (
                <p className="text-sm text-destructive">{getApiErrorMessage(logsQuery.error)}</p>
              ) : (() => {
                const failedRows = visibleRows.filter((r) =>
                  ["failed", "error"].includes(r.status.toLowerCase()),
                );
                return failedRows.length === 0 ? (
                  <p className="text-sm text-muted-foreground">No failed or error runs in this window. ✅</p>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>When (local)</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>HTTP</TableHead>
                        <TableHead className="text-right">Latency</TableHead>
                        <TableHead>Message</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {failedRows.map((row) => (
                        <TableRow key={row.id}>
                          <TableCell className="whitespace-nowrap text-sm">
                            {row.created_at ? new Date(row.created_at).toLocaleString() : "—"}
                          </TableCell>
                          <TableCell>
                            <Badge variant={statusVariant(row.status)}>{row.status}</Badge>
                          </TableCell>
                          <TableCell className="text-muted-foreground">{row.http_status ?? "—"}</TableCell>
                          <TableCell className="text-right text-muted-foreground">
                            {row.response_time_ms != null ? `${row.response_time_ms} ms` : "—"}
                          </TableCell>
                          <TableCell className="max-w-md truncate text-sm text-muted-foreground">
                            {row.message ?? "—"}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                );
              })()}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="server">
            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Server analytics</CardTitle>
                <CardDescription>
                  Aggregated from server monitor runs in this window (load, CPU, memory, disk, bandwidth, process count).
                </CardDescription>
              </CardHeader>
              <CardContent>
                {serverAnalyticsQuery.isLoading ? (
                  <Skeleton className="h-24 w-full" />
                ) : serverAnalyticsQuery.isError ? (
                  <p className="text-sm text-destructive">{getApiErrorMessage(serverAnalyticsQuery.error)}</p>
                ) : serverAnalyticsQuery.data ? (
                  <>
                    <div className="mb-3 rounded-lg border border-border/50 bg-muted/20 p-3">
                      <p className="text-xs text-muted-foreground">Samples</p>
                      <p className="text-lg font-semibold">{serverAnalyticsQuery.data.sample_count}</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                      {Object.entries(serverAnalyticsQuery.data.metrics).map(([metric, stats]) => (
                        <div key={metric} className="rounded-lg border border-border/50 bg-muted/20 p-3">
                          <p className="text-xs text-muted-foreground">{metric}</p>
                          <p className="text-sm">
                            avg {stats.avg ?? "-"} | min {stats.min ?? "-"} | max {stats.max ?? "-"}
                          </p>
                          <p className="text-sm font-medium">latest {stats.latest ?? "-"}</p>
                        </div>
                      ))}
                    </div>
                  </>
                ) : (
                  <p className="text-sm text-muted-foreground">
                    Server Analytics are only available for checks of type <strong className="text-foreground">server</strong>. This check is type <strong className="text-foreground">{checkQuery.data?.type ?? "unknown"}</strong>.
                  </p>
                )}
              </CardContent>
            </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
