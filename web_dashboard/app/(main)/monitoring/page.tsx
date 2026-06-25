"use client";

import Link from "next/link";
import { Suspense, useEffect, useMemo, useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { MoreHorizontal, Play, Plus } from "lucide-react";
import { toast } from "sonner";
import {
  fetchMonitoringChecks,
  MONITORING_CHECK_TYPES,
  type MonitoringCheckType,
  runMonitoringCheck,
} from "@/lib/api/monitoring";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import { formatUptimeSince } from "@/lib/format";
import type { MonitoringCheck } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { MonitoringCheckDeleteSheet } from "@/components/monitoring/monitoring-check-delete-sheet";
import { MonitoringCheckReassignSheet } from "@/components/monitoring/monitoring-check-reassign-sheet";
import { MonitoringCheckUpsertSheet } from "@/components/monitoring/monitoring-check-upsert-sheet";
import { useAuthStore } from "@/stores/auth-store";
import { useDashboardSettings } from "@/stores/dashboard-settings-store";
import { formatCheckStatusLabel } from "@/lib/monitoring/domain-expiry";

function statusVariant(status: string | null): "success" | "warning" | "destructive" | "secondary" {
  const s = (status ?? "").toLowerCase();
  if (s === "ok") return "success";
  if (s === "degraded") return "warning";
  if (s === "failed" || s === "error") return "destructive";
  return "secondary";
}

function rowToneClass(status: string | null): string {
  const s = (status ?? "").toLowerCase();
  if (s === "failed" || s === "error") return "bg-red-500/5";
  if (s === "degraded") return "bg-amber-500/8";
  return "";
}

function buildPageTokens(currentPage: number, lastPage: number): Array<number | "ellipsis"> {
  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, i) => i + 1);
  }

  const pages = new Set<number>([1, lastPage, currentPage - 1, currentPage, currentPage + 1]);

  if (currentPage <= 3) {
    pages.add(2);
    pages.add(3);
    pages.add(4);
  }

  if (currentPage >= lastPage - 2) {
    pages.add(lastPage - 1);
    pages.add(lastPage - 2);
    pages.add(lastPage - 3);
  }

  const ordered = Array.from(pages)
    .filter((n) => n >= 1 && n <= lastPage)
    .sort((a, b) => a - b);

  const tokens: Array<number | "ellipsis"> = [];
  for (let i = 0; i < ordered.length; i += 1) {
    const page = ordered[i];
    if (i > 0 && page - ordered[i - 1] > 1) {
      tokens.push("ellipsis");
    }
    tokens.push(page);
  }

  return tokens;
}

const CHECK_TYPE_TABS: Array<{ value: "all" | MonitoringCheckType; label: string }> = [
  { value: "all", label: "All" },
  ...MONITORING_CHECK_TYPES.map((type) => ({
    value: type,
    label: type === "https" ? "HTTPS" : type.toUpperCase(),
  })),
];

function MonitoringPageContent() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const isGlobalAdmin = Boolean(useAuthStore((s) => s.user?.is_admin));
  const monitoringPerPage = useDashboardSettings((s) => s.monitoringPerPage);

  const [upsertOpen, setUpsertOpen] = useState(false);
  const [upsertCheck, setUpsertCheck] = useState<MonitoringCheck | null>(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleteCheckRow, setDeleteCheckRow] = useState<MonitoringCheck | null>(null);
  const [reassignOpen, setReassignOpen] = useState(false);
  const [reassignCheck, setReassignCheck] = useState<MonitoringCheck | null>(null);
  const [search, setSearch] = useState("");
  const [checkType, setCheckType] = useState<"all" | MonitoringCheckType>("all");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState<number>(
    [12, 50, 100].includes(monitoringPerPage) ? monitoringPerPage : 50,
  );

  const showDomainExpiryInStatus = checkType === "domain" || checkType === "whois";

  const listParams = useMemo((): Record<string, string> => {
    const params: Record<string, string> = {
      per_page: String(perPage),
      page: String(page),
      search,
    };

    if (checkType !== "all") {
      params.type = checkType;
    }

    return params;
  }, [perPage, page, search, checkType]);

  const query = useQuery({
    queryKey: queryKeys.monitoringChecks(listParams),
    queryFn: () => fetchMonitoringChecks(listParams),
  });

  const meta = query.data?.meta;
  const currentPage = meta?.current_page ?? page;
  const lastPage = meta?.last_page ?? 1;
  const pageTokens = useMemo(() => buildPageTokens(currentPage, lastPage), [currentPage, lastPage]);

  useEffect(() => {
    const raw = searchParams?.get("type") ?? null;
    const next =
      raw && MONITORING_CHECK_TYPES.includes(raw as MonitoringCheckType)
        ? (raw as MonitoringCheckType)
        : "all";

    setCheckType((prev) => (prev === next ? prev : next));
  }, [searchParams]);

  const runMutation = useMutation({
    mutationFn: (id: string) => runMonitoringCheck(id),
    onSuccess: () => {
      toast.success("Run queued", {
        description:
          "The API pushed a job to Redis. The probe runs when Horizon (or a queue worker) picks it up—often within a few seconds. Refresh the table or wait for the auto-refresh.",
      });
      const inv = () => {
        void queryClient.invalidateQueries({ queryKey: ["monitoring-checks"] });
        void queryClient.invalidateQueries({ queryKey: ["monitoring-check-logs"] });
        void queryClient.invalidateQueries({ queryKey: ["monitoring-check-log-summary"] });
      };
      inv();
      window.setTimeout(inv, 3500);
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  function openCreate() {
    setUpsertCheck(null);
    setUpsertOpen(true);
  }

  function openEdit(c: MonitoringCheck) {
    setUpsertCheck(c);
    setUpsertOpen(true);
  }

  function openDelete(c: MonitoringCheck) {
    setDeleteCheckRow(c);
    setDeleteOpen(true);
  }

  function openReassign(c: MonitoringCheck) {
    setReassignCheck(c);
    setReassignOpen(true);
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Monitoring</h1>
      </div>
      <Tabs defaultValue="checks" className="w-full">
        <TabsList>
          <TabsTrigger value="checks">Checks</TabsTrigger>
          <TabsTrigger value="guide">How It Works</TabsTrigger>
        </TabsList>

        <TabsContent value="checks">
          <Card className="border-border/60">
            <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <CardTitle>Checks</CardTitle>
                <CardDescription>
                  Add checks here or queue a manual run. <strong className="font-medium text-foreground">Run</strong> dispatches a
                  Redis job; the probe executes when Horizon processes the <code className="rounded bg-muted px-1 text-xs">site-monitoring</code> queue.
                  {checkType === "domain" || checkType === "whois" ? (
                    <>
                      {" "}
                      Domain and WHOIS checks show registration expiry from RDAP; alerts fire when within 30 days.
                    </>
                  ) : null}
                </CardDescription>
              </div>
              <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                <Input
                  placeholder="Search checks, endpoint, status..."
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    setPage(1);
                  }}
                  className="w-full sm:w-[280px]"
                />
                <select
                  aria-label="Rows per page"
                  className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                  value={String(perPage)}
                  onChange={(e) => {
                    setPerPage(Number(e.target.value));
                    setPage(1);
                  }}
                >
                  <option value="12">12 / page</option>
                  <option value="50">50 / page</option>
                  <option value="100">100 / page</option>
                </select>
                <Button type="button" className="shrink-0 self-start sm:self-auto" onClick={openCreate}>
                  <Plus className="size-4" />
                  Add check
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <Tabs
                value={checkType}
                onValueChange={(value) => {
                  const nextType = value as "all" | MonitoringCheckType;
                  setCheckType(nextType);
                  setPage(1);

                  const params = new URLSearchParams(searchParams?.toString() ?? "");
                  const currentPath = pathname ?? "/monitoring";
                  if (nextType === "all") {
                    params.delete("type");
                  } else {
                    params.set("type", nextType);
                  }
                  params.delete("page");

                  const queryString = params.toString();
                  router.replace(queryString ? `${currentPath}?${queryString}` : currentPath);
                }}
                className="mb-4"
              >
                <TabsList className="h-auto flex-wrap justify-start gap-1">
                  {CHECK_TYPE_TABS.map((tab) => (
                    <TabsTrigger key={tab.value} value={tab.value} className="h-8 px-2.5 text-xs">
                      {tab.label}
                    </TabsTrigger>
                  ))}
                </TabsList>
              </Tabs>

              {query.isLoading ? (
                <Skeleton className="h-64 w-full" />
              ) : query.isError ? (
                <p className="text-sm text-destructive">{getApiErrorMessage(query.error)}</p>
              ) : (
                <>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Endpoint</TableHead>
                        <TableHead>Enabled</TableHead>
                        <TableHead>Last status</TableHead>
                        <TableHead>Up since</TableHead>
                        <TableHead className="text-right">Latency</TableHead>
                        <TableHead className="w-[100px] text-right">Run</TableHead>
                        <TableHead className="w-[52px] text-right" />
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {query.data?.items.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={9} className="text-center text-sm text-muted-foreground">
                            No monitoring checks yet. Use <strong className="text-foreground">Add check</strong> to create
                            one.
                          </TableCell>
                        </TableRow>
                      ) : (
                        query.data?.items.map((c) => (
                          <TableRow key={c.id} className={rowToneClass(c.last_status)}>
                            <TableCell className="font-medium">{c.name}</TableCell>
                            <TableCell className="text-muted-foreground">{c.type}</TableCell>
                            <TableCell className="max-w-[200px] truncate text-muted-foreground">{c.endpoint ?? "—"}</TableCell>
                            <TableCell>
                              <Badge variant={c.enabled ? "success" : "secondary"}>{c.enabled ? "On" : "Off"}</Badge>
                            </TableCell>
                            <TableCell>
                              <Badge variant={statusVariant(c.last_status)}>
                                {formatCheckStatusLabel(c, { showDomainExpiryDays: showDomainExpiryInStatus })}
                              </Badge>
                            </TableCell>
                            <TableCell className="whitespace-nowrap text-sm text-muted-foreground">
                              {c.last_status === "ok" && c.uptime_since
                                ? formatUptimeSince(c.uptime_since)
                                : "—"}
                            </TableCell>
                            <TableCell className="text-right text-muted-foreground">
                              {c.last_response_time_ms != null ? `${c.last_response_time_ms} ms` : "—"}
                            </TableCell>
                            <TableCell className="text-right">
                              <Button
                                size="sm"
                                variant="outline"
                                disabled={runMutation.isPending}
                                onClick={() => runMutation.mutate(c.id)}
                              >
                                <Play className="h-3.5 w-3.5" />
                                Run
                              </Button>
                            </TableCell>
                            <TableCell className="text-right">
                              <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                  <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                                    <span className="sr-only">Open menu</span>
                                    <MoreHorizontal className="size-4" />
                                  </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                  <DropdownMenuItem asChild>
                                    <Link href={`/monitoring/${c.id}/history`}>History &amp; uptime</Link>
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onClick={() => openEdit(c)}>Edit</DropdownMenuItem>
                                  {isGlobalAdmin ? (
                                    <DropdownMenuItem onClick={() => openReassign(c)}>
                                      Reassign organization
                                    </DropdownMenuItem>
                                  ) : null}
                                  <DropdownMenuItem
                                    className="text-destructive focus:text-destructive"
                                    onClick={() => openDelete(c)}
                                  >
                                    Delete
                                  </DropdownMenuItem>
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </TableCell>
                          </TableRow>
                        ))
                      )}
                    </TableBody>
                  </Table>
                  {lastPage > 1 ? (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                      <p className="text-xs text-muted-foreground">
                        Page {currentPage} of {lastPage}
                      </p>
                      <div className="flex flex-wrap items-center gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={currentPage <= 1 || query.isFetching}
                          onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                          Previous
                        </Button>
                        {pageTokens.map((token, idx) =>
                          token === "ellipsis" ? (
                            <span key={`ellipsis-${idx}`} className="px-1 text-sm text-muted-foreground">
                              ...
                            </span>
                          ) : (
                            <Button
                              key={token}
                              type="button"
                              variant={token === currentPage ? "default" : "outline"}
                              size="sm"
                              disabled={query.isFetching}
                              onClick={() => setPage(token)}
                              aria-current={token === currentPage ? "page" : undefined}
                            >
                              {token}
                            </Button>
                          ),
                        )}
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={currentPage >= lastPage || query.isFetching}
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

        <TabsContent value="guide">
          <div className="space-y-4 text-sm leading-relaxed text-muted-foreground">
            <p>
              Create uptime, SSL, HTTP(S), and other checks for the active organization. Runs use your Laravel queue
              (Horizon / site-monitoring).
            </p>
            <div className="rounded-lg border border-border/60 bg-muted/25 p-4">
              <p className="font-medium text-foreground">How you know when something is down</p>
              <ul className="mt-2 list-inside list-disc space-y-1.5">
                <li>
                  <span className="text-foreground">This table</span> — <strong className="font-medium text-foreground">Last status</strong>{" "}
                  shows <code className="rounded bg-muted px-1 text-xs">failed</code>,{" "}
                  <code className="rounded bg-muted px-1 text-xs">error</code>, or{" "}
                  <code className="rounded bg-muted px-1 text-xs">degraded</code> after a worker has finished the probe.{" "}
                  <strong className="font-medium text-foreground">Run</strong> only enqueues work: the API returns right away, and the
                  check runs when <code className="rounded bg-muted px-1 text-xs">php artisan horizon</code> (or{" "}
                  <code className="rounded bg-muted px-1 text-xs">queue:work</code>) processes the{" "}
                  <code className="rounded bg-muted px-1 text-xs">site-monitoring</code> queue—often within seconds if workers are up.
                  If nothing is consuming that queue, the row will not change until a worker runs.
                </li>
                <li>
                  <span className="text-foreground">Dashboard</span> — The monitoring snapshot and alert counts include checks
                  in those states (Redis + Horizon must be running for schedules).
                </li>
                <li>
                  <span className="text-foreground">Notifications</span> — For <code className="rounded bg-muted px-1 text-xs">http</code>,{" "}
                  <code className="rounded bg-muted px-1 text-xs">https</code>, and{" "}
                  <code className="rounded bg-muted px-1 text-xs">uptime</code> types, the API can emit in-app notifications when
                  failures reach the streak threshold (see <code className="rounded bg-muted px-1 text-xs">SITE_MONITORING_UPTIME_NOTIFY_AFTER_FAILURES</code> in{" "}
                  <code className="rounded bg-muted px-1 text-xs">config/site-monitoring.php</code>).
                </li>
                <li>
                  <span className="text-foreground">Sample data</span> — From the API project root:{" "}
                  <code className="rounded bg-muted px-1 font-mono text-xs">php artisan db:seed --class=MonitoringDemoSeeder</code>{" "}
                  creates <code className="rounded bg-muted px-1 text-xs">[Demo]</code> rows on <strong className="text-foreground">Demo Organization</strong>{" "}
                  (safe to re-run; it replaces previous demo checks). Full <code className="rounded bg-muted px-1 font-mono text-xs">php artisan db:seed</code> includes
                  the same step after the dev user is created or refreshed.
                </li>
              </ul>
            </div>
          </div>
        </TabsContent>
      </Tabs>

      <MonitoringCheckUpsertSheet open={upsertOpen} onOpenChange={setUpsertOpen} check={upsertCheck} />

      <MonitoringCheckDeleteSheet
        check={deleteCheckRow}
        open={deleteOpen}
        onOpenChange={(open) => {
          setDeleteOpen(open);
          if (!open) setDeleteCheckRow(null);
        }}
      />

      <MonitoringCheckReassignSheet
        check={reassignCheck}
        open={reassignOpen}
        onOpenChange={(open) => {
          setReassignOpen(open);
          if (!open) setReassignCheck(null);
        }}
      />
    </div>
  );
}

export default function MonitoringPage() {
  return (
    <Suspense fallback={<Skeleton className="mx-auto h-64 max-w-7xl w-full" />}>
      <MonitoringPageContent />
    </Suspense>
  );
}
