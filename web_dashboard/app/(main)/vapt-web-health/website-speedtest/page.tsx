"use client";

import { useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { ArrowLeft, Download, Gauge, Play, ShieldAlert } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { getApiErrorMessage } from "@/lib/api/errors";
import { fetchWebsiteSpeedtestRuns, runWebsiteSpeedtest } from "@/lib/api/vapt-web-health";
import { queryKeys } from "@/lib/api/query-keys";
import type { WebsiteSpeedtestReport, WebsiteSpeedtestRun } from "@/lib/api/types";
import { useAuthStore, useOrganizations } from "@/stores/auth-store";

function downloadTextFile(fileName: string, content: string, mimeType: string): void {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = fileName;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}

function toRunsCsv(runs: WebsiteSpeedtestRun[]): string {
  const headers = [
    "tested_at",
    "organization_name",
    "checked_from",
    "target_url",
    "final_url",
    "status_code",
    "success",
    "total_time_ms",
    "ttfb_ms",
    "dns_lookup_ms",
    "tcp_connect_ms",
    "tls_handshake_ms",
    "redirect_time_ms",
    "download_speed_kbps",
    "downloaded_bytes",
    "error",
  ];

  const escapeValue = (value: string | number | boolean | null): string => {
    if (value === null) return "";
    const raw = String(value);
    if (raw.includes(",") || raw.includes("\n") || raw.includes('"')) {
      return `"${raw.replaceAll('"', '""')}"`;
    }
    return raw;
  };

  const rows = runs.map((row) =>
    [
      row.tested_at,
      row.organization_name ?? null,
      row.checked_from,
      row.target_url,
      row.final_url,
      row.status_code,
      row.success,
      row.total_time_ms,
      row.ttfb_ms,
      row.dns_lookup_ms,
      row.tcp_connect_ms,
      row.tls_handshake_ms,
      row.redirect_time_ms,
      row.download_speed_kbps,
      row.downloaded_bytes,
      row.error,
    ]
      .map(escapeValue)
      .join(","),
  );

  return [headers.join(","), ...rows].join("\n");
}

function fmtMs(value: number | null): string {
  return value != null ? `${value} ms` : "-";
}

function fmtKbps(value: number | null): string {
  return value != null ? `${value} kbps` : "-";
}

function fmtBytes(value: number | null): string {
  return value != null ? `${value.toLocaleString()} bytes` : "-";
}

export default function WebsiteSpeedtestPage() {
  const organizationId = useAuthStore((s) => s.organizationId);
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const organizations = useOrganizations();
  const isAllOrganizationsMode = isAdmin && organizationId === null;

  const [targetUrl, setTargetUrl] = useState("");
  const [timeoutSeconds, setTimeoutSeconds] = useState("20");
  const [writeOrganizationId, setWriteOrganizationId] = useState("");
  const [report, setReport] = useState<WebsiteSpeedtestReport | null>(null);
  const selectedWriteOrganizationId = writeOrganizationId;

  const resolveOrganizationName = (id?: string): string | null => {
    if (!id) return null;
    return organizations.find((org) => org.id === id)?.name ?? null;
  };

  const mutation = useMutation({
    mutationFn: ({ payload, organizationId }: { payload: { target_url: string; timeout_seconds: number }; organizationId?: string }) =>
      runWebsiteSpeedtest(payload, { organizationId }),
    onSuccess: (data, variables) => {
      setReport(data);

      const savedOrgName = isAllOrganizationsMode
        ? resolveOrganizationName(variables.organizationId)
        : resolveOrganizationName(organizationId ?? undefined);

      if (data.persisted === false) {
        toast.warning(data.persistence_note ?? "Speedtest completed but result was not saved.");
        return;
      }

      toast.success(
        savedOrgName
          ? `Website speedtest completed. Saved under: ${savedOrgName}`
          : "Website speedtest completed",
      );
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const metrics = report?.metrics;
  const runsQuery = useQuery({
    queryKey: queryKeys.websiteSpeedtestRuns({
      limit: "40",
      target_url: targetUrl.trim() || "", 
      all_organizations: isAllOrganizationsMode ? "1" : "", // 🚀 FIX: Fallback to empty string
    }),
    queryFn: () =>
      fetchWebsiteSpeedtestRuns({
        limit: 40,
        target_url: targetUrl.trim() || "", // 🚀 FIX: Fallback to empty string instead of undefined
        all_organizations: isAllOrganizationsMode ? 1 : 0, // 🚀 FIX: Fallback to 0 (or your API's expected off-state number)
      }),
  });
  const runs = runsQuery.data ?? [];

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1 px-2" asChild>
          <Link href="/vapt-web-health">
            <ArrowLeft className="h-4 w-4" />
            VAPT &amp; Web Health
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">Website Speedtest</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Measure website response and load behavior for external URLs from the VAPT workspace.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Gauge className="h-5 w-5" />
            Speed test console
          </CardTitle>
          <CardDescription>
            Run a live request and collect timing metrics like total time, TTFB, DNS lookup, and connection stages.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {isAllOrganizationsMode ? (
            <div className="rounded-lg border border-warning/50 bg-warning/10 p-3 text-sm text-warning-foreground">
              Global admin mode is active. You can run a speedtest without saving, or choose an organization below
              to save results when the domain belongs to that organization.
            </div>
          ) : null}
          {isAllOrganizationsMode ? (
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground" htmlFor="speedtest-write-org">
                Save this run under organization (optional)
              </label>
              <select
                id="speedtest-write-org"
                className="h-9 min-w-[260px] rounded-md border border-input bg-background px-3 py-1 text-sm"
                value={selectedWriteOrganizationId}
                onChange={(e) => setWriteOrganizationId(e.target.value)}
              >
                <option value="">Do not save (run only)</option>
                {organizations.map((org) => (
                  <option key={org.id} value={org.id}>
                    {org.name}
                  </option>
                ))}
              </select>
            </div>
          ) : null}
          <div className="grid gap-3 md:grid-cols-[1fr_180px_auto]">
            <Input
              placeholder="https://example.com"
              value={targetUrl}
              onChange={(e) => setTargetUrl(e.target.value)}
            />
            <Input
              type="number"
              min={5}
              max={60}
              value={timeoutSeconds}
              onChange={(e) => setTimeoutSeconds(e.target.value)}
            />
            <Button
              onClick={() =>
                mutation.mutate({
                  payload: {
                    target_url: targetUrl,
                    timeout_seconds: Number(timeoutSeconds) || 20,
                  },
                  organizationId:
                    isAllOrganizationsMode && selectedWriteOrganizationId
                      ? selectedWriteOrganizationId
                      : undefined,
                })
              }
              disabled={mutation.isPending || targetUrl.trim() === ""}
            >
              <Play className="size-4" />
              {mutation.isPending ? "Running..." : "Run Speedtest"}
            </Button>
          </div>
          <p className="text-xs text-muted-foreground">
            Timeout is in seconds (5-60). Use only domains you own or are authorized to test.
          </p>
        </CardContent>
      </Card>

      {report ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Total time</CardDescription>
                <CardTitle className="text-2xl">{fmtMs(metrics?.total_time_ms ?? null)}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>TTFB</CardDescription>
                <CardTitle className="text-2xl">{fmtMs(metrics?.ttfb_ms ?? null)}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Status code</CardDescription>
                <CardTitle className="text-2xl">{report.status_code ?? "-"}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Download speed</CardDescription>
                <CardTitle className="text-2xl">{fmtKbps(metrics?.download_speed_kbps ?? null)}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60 sm:col-span-2 lg:col-span-4">
              <CardHeader className="pb-2">
                <CardDescription>Checked from</CardDescription>
                <CardTitle className="text-base">
                  {report.checked_from ?? "-"}
                </CardTitle>
                <div>
                  <span
                    className={
                      report.persisted === false
                        ? "inline-flex items-center rounded-full border border-warning/50 bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning-foreground"
                        : "inline-flex items-center rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700"
                    }
                  >
                    {report.persisted === false ? "Not saved" : "Saved"}
                  </span>
                </div>
              </CardHeader>
            </Card>
          </div>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Detailed metrics</CardTitle>
              <CardDescription>Low-level timing data from the live HTTP request.</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-lg border border-border/60 p-3 text-sm">
                <p className="text-xs text-muted-foreground">DNS lookup</p>
                <p className="font-medium">{fmtMs(metrics?.dns_lookup_ms ?? null)}</p>
              </div>
              <div className="rounded-lg border border-border/60 p-3 text-sm">
                <p className="text-xs text-muted-foreground">TCP connect</p>
                <p className="font-medium">{fmtMs(metrics?.tcp_connect_ms ?? null)}</p>
              </div>
              <div className="rounded-lg border border-border/60 p-3 text-sm">
                <p className="text-xs text-muted-foreground">TLS handshake</p>
                <p className="font-medium">{fmtMs(metrics?.tls_handshake_ms ?? null)}</p>
              </div>
              <div className="rounded-lg border border-border/60 p-3 text-sm">
                <p className="text-xs text-muted-foreground">Redirect time</p>
                <p className="font-medium">{fmtMs(metrics?.redirect_time_ms ?? null)}</p>
              </div>
              <div className="rounded-lg border border-border/60 p-3 text-sm sm:col-span-2">
                <p className="text-xs text-muted-foreground">Downloaded bytes</p>
                <p className="font-medium">{fmtBytes(metrics?.downloaded_bytes ?? null)}</p>
              </div>
            </CardContent>
          </Card>

          <details className="rounded-lg border border-border/60 p-3">
            <summary className="cursor-pointer text-sm font-medium">View JSON payload</summary>
            <pre className="mt-2 max-h-80 overflow-auto rounded bg-muted p-3 text-xs">
              {JSON.stringify(report, null, 2)}
            </pre>
          </details>
        </>
      ) : null}

      <Card className="border-border/60">
        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle>Recent trend</CardTitle>
            <CardDescription>
              {isAllOrganizationsMode
                ? "Latest saved runs across all organizations (admin read-only mode)."
                : "Latest saved runs for this organization."}
              {targetUrl.trim() ? " Filtered by current target URL." : ""}
            </CardDescription>
          </div>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              disabled={runs.length === 0}
              onClick={() =>
                downloadTextFile(
                  "website-speedtest-runs.json",
                  JSON.stringify(runs, null, 2),
                  "application/json;charset=utf-8",
                )
              }
            >
              <Download className="size-4" />
              Export JSON
            </Button>
            <Button
              type="button"
              variant="outline"
              disabled={runs.length === 0}
              onClick={() =>
                downloadTextFile(
                  "website-speedtest-runs.csv",
                  toRunsCsv(runs),
                  "text/csv;charset=utf-8",
                )
              }
            >
              <Download className="size-4" />
              Export CSV
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {runsQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading recent runs...</p>
          ) : runsQuery.isError ? (
            <p className="text-sm text-destructive">{getApiErrorMessage(runsQuery.error)}</p>
          ) : runs.length === 0 ? (
            <p className="text-sm text-muted-foreground">No stored speedtest runs yet.</p>
          ) : (
            <div className="space-y-4">
              <div className="grid gap-2">
                {runs
                  .slice()
                  .reverse()
                  .map((run) => {
                    const val = run.total_time_ms ?? 0;
                    const max = Math.max(...runs.map((r) => r.total_time_ms ?? 0), 1);
                    const pct = Math.max(3, Math.round((val / max) * 100));

                    return (
                      <div key={run.id} className="space-y-1">
                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                          <span>{run.tested_at ? new Date(run.tested_at).toLocaleString() : "-"}</span>
                          <span>{run.total_time_ms != null ? `${run.total_time_ms} ms` : "-"}</span>
                        </div>
                        <div className="h-2 rounded bg-muted">
                          <div className="h-2 rounded bg-primary" style={{ width: `${pct}%` }} />
                        </div>
                      </div>
                    );
                  })}
              </div>

              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>When</TableHead>
                      {isAllOrganizationsMode ? <TableHead>Organization</TableHead> : null}
                      <TableHead>Checked from</TableHead>
                      <TableHead>Total</TableHead>
                      <TableHead>TTFB</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>URL</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {runs.map((run) => (
                      <TableRow key={run.id}>
                        <TableCell className="whitespace-nowrap text-xs text-muted-foreground">
                          {run.tested_at ? new Date(run.tested_at).toLocaleString() : "-"}
                        </TableCell>
                        {isAllOrganizationsMode ? (
                          <TableCell className="max-w-[180px] truncate text-xs text-muted-foreground">
                            {run.organization_name ?? run.organization_id}
                          </TableCell>
                        ) : null}
                        <TableCell className="max-w-[220px] truncate text-xs text-muted-foreground">
                          {run.checked_from ?? "-"}
                        </TableCell>
                        <TableCell>{fmtMs(run.total_time_ms)}</TableCell>
                        <TableCell>{fmtMs(run.ttfb_ms)}</TableCell>
                        <TableCell>{run.status_code ?? "-"}</TableCell>
                        <TableCell className="max-w-[320px] truncate text-xs text-muted-foreground">
                          {run.target_url}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      <Card className="border-border/60 bg-muted/20">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <ShieldAlert className="h-4 w-4" />
            Safety note
          </CardTitle>
          <CardDescription>
            Keep checks against authorized systems only and avoid high-frequency scans on third-party infrastructure.
          </CardDescription>
        </CardHeader>
      </Card>
    </div>
  );
}