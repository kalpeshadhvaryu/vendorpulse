"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useParams } from "next/navigation";
import { AlertTriangle, ArrowLeft } from "lucide-react";
import axios from "axios";
import { api } from "@/lib/api/client";
import {
  useExperienceMonitoringMetrics,
  useExperienceMonitoringReport,
  useExperienceMonitoringRuns,
  useExperienceMonitoringScreenshots,
  useExperienceMonitoringTest,
} from "@/hooks/use-experience-monitoring";
import { getApiErrorMessage } from "@/lib/api/errors";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { ExperienceMonitoringRun, ExperienceMonitoringTechnicalReport } from "@/lib/api/types";
import { useAuthStore } from "@/stores/auth-store";

type BrowserLogEntry = {
  level?: string;
  source?: string;
  message?: string;
  at?: string;
};

function statusVariant(status: string): "success" | "warning" | "destructive" | "secondary" {
  const value = status.toLowerCase();
  if (value === "ok") return "success";
  if (value === "slow_dashboard" || value === "js_error") return "warning";
  if (value === "failed_login" || value === "timeout" || value === "error") return "destructive";
  return "secondary";
}

function tinySeries(values: Array<number | null>) {
  const clean = values.filter((v): v is number => typeof v === "number");
  if (clean.length === 0) return [];
  const max = Math.max(...clean, 1);
  return clean.map((v, i) => ({ x: i, y: Math.max(4, Math.round((v / max) * 60)) }));
}

function ScreenshotTile({
  screenshotId,
  capturedAt,
  targetUrl,
  token,
  organizationHeaderId,
}: {
  screenshotId: string;
  capturedAt: string | null;
  targetUrl: string | null;
  token: string | null;
  organizationHeaderId: string;
}) {
  const [blobUrl, setBlobUrl] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [retryIndex, setRetryIndex] = useState(0);

  useEffect(() => {
    if (!token) {
      setBlobUrl(null);
      setErrorMessage("Sign in to load screenshots.");
      return;
    }

    let isMounted = true;
    let urlToRevoke: string | null = null;

    (async () => {
      try {
        setLoading(true);
        setErrorMessage(null);

        const response = await api.get(`/experience-monitoring-screenshots/${screenshotId}/file`, {
          responseType: "blob",
          headers: {
            "X-Suppress-Server-Toast": "1",
            ...(organizationHeaderId ? { "X-Organization-Id": organizationHeaderId } : {}),
          },
        });

        if (!isMounted) {
          return;
        }

        const objectUrl = URL.createObjectURL(response.data);
        urlToRevoke = objectUrl;
        setBlobUrl(objectUrl);
      } catch (error: unknown) {
        if (!isMounted) {
          return;
        }

        setBlobUrl(null);
        if (axios.isAxiosError(error) && error.response?.data instanceof Blob) {
          try {
            const text = await error.response.data.text();
            const parsed = JSON.parse(text) as { message?: string };
            setErrorMessage(parsed.message ?? getApiErrorMessage(error));
          } catch {
            setErrorMessage(getApiErrorMessage(error));
          }
        } else {
          setErrorMessage(getApiErrorMessage(error));
        }
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    })();

    return () => {
      isMounted = false;
      if (urlToRevoke) {
        URL.revokeObjectURL(urlToRevoke);
      }
    };
  }, [screenshotId, token, organizationHeaderId, retryIndex]);

  return (
    <div className="overflow-hidden rounded-md border border-border/60 bg-muted/10">
      {loading ? (
        <div className="flex h-44 items-center justify-center text-xs text-muted-foreground">Loading screenshot...</div>
      ) : blobUrl ? (
        <a href={blobUrl} target="_blank" rel="noreferrer">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={blobUrl} alt={`Screenshot ${screenshotId}`} className="h-44 w-full object-cover" loading="lazy" />
        </a>
      ) : (
        <div className="flex h-44 flex-col items-center justify-center gap-2 px-3 text-center text-xs text-muted-foreground">
          <span>{errorMessage ?? "Screenshot unavailable"}</span>
          <Button type="button" size="sm" variant="outline" onClick={() => setRetryIndex((n) => n + 1)}>
            Retry
          </Button>
        </div>
      )}
      <p className="truncate px-2 py-1 text-xs text-muted-foreground">
        {capturedAt ? new Date(capturedAt).toLocaleString() : "Screenshot"}
      </p>
      <p className="truncate px-2 pb-2 text-xs text-muted-foreground">
        {targetUrl ? (
          <a href={targetUrl} target="_blank" rel="noreferrer" className="underline-offset-2 hover:underline">
            {targetUrl}
          </a>
        ) : (
          "URL unavailable"
        )}
      </p>
    </div>
  );
}

function extractJsErrorMessages(run: ExperienceMonitoringRun): string[] {
  const fromLogs = (run.browser_logs ?? [])
    .filter((entry): entry is BrowserLogEntry => typeof entry === "object" && entry !== null)
    .filter((entry) => String(entry.level ?? "").toLowerCase() === "error")
    .map((entry) => String(entry.message ?? "").trim())
    .filter(Boolean);

  const merged = run.error_message ? [run.error_message, ...fromLogs] : fromLogs;
  return Array.from(new Set(merged));
}

function buildRunSuggestions(run: ExperienceMonitoringRun): string[] {
  const suggestions = new Set<string>();
  const messages = extractJsErrorMessages(run).map((msg) => msg.toLowerCase());
  const joined = messages.join(" ");

  if (messages.length === 0 && run.failed_requests_count > 0) {
    suggestions.add("Review failed network requests for blocked or unreachable assets.");
  }

  if (joined.includes("script") && joined.includes("error")) {
    suggestions.add("Open the screenshot and verify whether key JavaScript bundles were loaded correctly.");
  }

  if (joined.includes("timeout")) {
    suggestions.add("Increase timeout_ms for this test, then rerun to confirm if slow loading is the root cause.");
  }

  if (joined.includes("401") || joined.includes("403") || joined.includes("unauthorized") || joined.includes("forbidden")) {
    suggestions.add("Check session/auth state after login and ensure protected API calls are returning authorized responses.");
  }

  if (joined.includes("404") || joined.includes("not found")) {
    suggestions.add("Verify route paths and static asset URLs referenced by the page are valid in this environment.");
  }

  if (joined.includes("cors") || joined.includes("cross-origin")) {
    suggestions.add("Inspect CORS and cookie settings for API domains used by the dashboard.");
  }

  if (joined.includes("cannot read") || joined.includes("undefined") || joined.includes("null")) {
    suggestions.add("Check recent frontend deployment changes for null/undefined handling regressions in dashboard code.");
  }

  if (run.failed_requests_count > 0) {
    suggestions.add("Inspect failed_requests_count and browser logs to identify the first failing endpoint.");
  }

  if (run.js_errors_count > 0) {
    suggestions.add("Compare this failing run with the previous successful run to isolate newly introduced JS changes.");
  }

  if (suggestions.size === 0) {
    suggestions.add("Rerun the test and compare logs + screenshot with a successful run for the same environment.");
  }

  return Array.from(suggestions);
}

export default function ExperienceMonitoringDetailPage() {
  const params = useParams();
  const testId = typeof params?.testId === "string" ? params.testId : "";

  const defaultTo = useMemo(() => new Date(), []);
  const defaultFrom = useMemo(() => new Date(Date.now() - 7 * 24 * 3600 * 1000), []);

  const [from, setFrom] = useState(defaultFrom.toISOString().slice(0, 16));
  const [to, setTo] = useState(defaultTo.toISOString().slice(0, 16));
  const [activeTab, setActiveTab] = useState("history");
  const [selectedRun, setSelectedRun] = useState<ExperienceMonitoringRun | null>(null);

  const token = useAuthStore((state) => state.token);
  const organizationId = useAuthStore((state) => state.organizationId);

  const filters = useMemo(() => ({ from, to, per_page: "30" }), [from, to]);

  const testQuery = useExperienceMonitoringTest(testId);
  const runsQuery = useExperienceMonitoringRuns(testId, filters);
  const metricsQuery = useExperienceMonitoringMetrics(testId, { from, to });
  const reportQuery = useExperienceMonitoringReport(testId, { from, to });
  const screenshotsQuery = useExperienceMonitoringScreenshots(testId, { per_page: "24" });

  const dashboardSeries = tinySeries(
    (runsQuery.data?.items ?? []).map((run) => run.dashboard_load_duration_ms),
  );

  const screenshotByRunId = useMemo(() => {
    const map = new Map<string, string>();
    for (const shot of screenshotsQuery.data?.items ?? []) {
      map.set(shot.experience_monitoring_run_id, shot.id);
    }
    return map;
  }, [screenshotsQuery.data?.items]);

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1" asChild>
          <Link href="/monitoring/experience">
            <ArrowLeft className="h-4 w-4" />
            Experience Monitoring
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">{testQuery.data?.name ?? "Experience Test"}</h1>
        <p className="text-sm text-muted-foreground">Execution history, metrics, logs, and screenshots.</p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="text-base">Window</CardTitle>
          <CardDescription>Use date range to inspect performance and reliability trends.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-3">
          <Input type="datetime-local" value={from} onChange={(e) => setFrom(e.target.value)} className="w-auto" />
          <Input type="datetime-local" value={to} onChange={(e) => setTo(e.target.value)} className="w-auto" />
        </CardContent>
      </Card>

      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="history">Execution History</TabsTrigger>
          <TabsTrigger value="metrics">Performance Graphs</TabsTrigger>
          <TabsTrigger value="report">VAPT Report</TabsTrigger>
          <TabsTrigger value="screenshots">Screenshots</TabsTrigger>
        </TabsList>

        <TabsContent value="history">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle className="text-base">Runs</CardTitle>
            </CardHeader>
            <CardContent>
              {runsQuery.isError ? (
                <p className="text-sm text-destructive">{getApiErrorMessage(runsQuery.error)}</p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>When</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Login</TableHead>
                      <TableHead>Dashboard</TableHead>
                      <TableHead>Errors</TableHead>
                      <TableHead>Failed Requests</TableHead>
                      <TableHead>Screenshot</TableHead>
                      <TableHead>JS Details</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(runsQuery.data?.items ?? []).map((run) => (
                      <TableRow key={run.id}>
                        <TableCell>{run.created_at ? new Date(run.created_at).toLocaleString() : "-"}</TableCell>
                        <TableCell>
                          <Badge variant={statusVariant(run.status)}>{run.status}</Badge>
                        </TableCell>
                        <TableCell>{run.login_duration_ms ?? "-"} ms</TableCell>
                        <TableCell>{run.dashboard_load_duration_ms ?? "-"} ms</TableCell>
                        <TableCell>{run.js_errors_count}</TableCell>
                        <TableCell>{run.failed_requests_count}</TableCell>
                        <TableCell>
                          {run.screenshot_path ? (
                            <Button type="button" variant="outline" size="sm" onClick={() => setActiveTab("screenshots")}>
                              View
                            </Button>
                          ) : (
                            <span className="text-xs text-muted-foreground">Missing</span>
                          )}
                        </TableCell>
                        <TableCell>
                          {run.js_errors_count > 0 || run.error_message ? (
                            <Button type="button" variant="outline" size="sm" onClick={() => setSelectedRun(run)}>
                              Explain
                            </Button>
                          ) : (
                            <span className="text-xs text-muted-foreground">-</span>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="metrics">
          <div className="grid gap-4 lg:grid-cols-3">
            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Dashboard Load</CardTitle>
                <CardDescription>
                  Avg: {metricsQuery.data?.dashboard_load_duration_ms.avg ?? "-"} ms, P95: {metricsQuery.data?.dashboard_load_duration_ms.p95 ?? "-"} ms
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex h-20 items-end gap-1">
                  {dashboardSeries.length === 0 ? (
                    <p className="text-xs text-muted-foreground">No points in selected range.</p>
                  ) : (
                    dashboardSeries.map((point) => (
                      <div key={point.x} className="w-2 rounded-sm bg-primary/70" style={{ height: `${point.y}px` }} />
                    ))
                  )}
                </div>
              </CardContent>
            </Card>

            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Login Duration</CardTitle>
              </CardHeader>
              <CardContent className="space-y-1 text-sm text-muted-foreground">
                <p>Average: {metricsQuery.data?.login_duration_ms.avg ?? "-"} ms</p>
                <p>Min: {metricsQuery.data?.login_duration_ms.min ?? "-"} ms</p>
                <p>Max: {metricsQuery.data?.login_duration_ms.max ?? "-"} ms</p>
                <p>P95: {metricsQuery.data?.login_duration_ms.p95 ?? "-"} ms</p>
              </CardContent>
            </Card>

            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Overall Response</CardTitle>
              </CardHeader>
              <CardContent className="space-y-1 text-sm text-muted-foreground">
                <p>Average: {metricsQuery.data?.total_duration_ms.avg ?? "-"} ms</p>
                <p>Min: {metricsQuery.data?.total_duration_ms.min ?? "-"} ms</p>
                <p>Max: {metricsQuery.data?.total_duration_ms.max ?? "-"} ms</p>
                <p>P95: {metricsQuery.data?.total_duration_ms.p95 ?? "-"} ms</p>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="report">
          <VaptReportPanel report={reportQuery.data} isError={reportQuery.isError} errorMessage={getApiErrorMessage(reportQuery.error)} />
        </TabsContent>

        <TabsContent value="screenshots">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle className="text-base">Recent Screenshots</CardTitle>
              <CardDescription>Captured after login/dashboard navigation for each run.</CardDescription>
            </CardHeader>
            <CardContent>
              {screenshotsQuery.isError ? (
                <p className="text-sm text-destructive">{getApiErrorMessage(screenshotsQuery.error)}</p>
              ) : (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {(screenshotsQuery.data?.items ?? []).map((shot) => (
                    <ScreenshotTile
                      key={shot.id}
                      screenshotId={shot.id}
                      capturedAt={shot.captured_at}
                      targetUrl={testQuery.data?.dashboard_url ?? testQuery.data?.login_url ?? null}
                      token={token}
                      organizationHeaderId={shot.organization_id}
                    />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={Boolean(selectedRun)} onOpenChange={(open) => (!open ? setSelectedRun(null) : null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-base">
              <AlertTriangle className="h-4 w-4 text-amber-500" />
              JavaScript Error Details
            </DialogTitle>
            <DialogDescription>
              Run: {selectedRun?.id} {selectedRun?.created_at ? `(${new Date(selectedRun.created_at).toLocaleString()})` : ""}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3 text-sm">
            {selectedRun ? (
              <>
                {extractJsErrorMessages(selectedRun).length > 0 ? (
                  <div className="space-y-2 rounded-md border border-border/60 p-3">
                    <p className="font-medium">Detected Errors</p>
                    {extractJsErrorMessages(selectedRun).map((msg, idx) => (
                      <p key={`${selectedRun.id}-${idx}`} className="rounded bg-muted/50 p-2 text-xs leading-relaxed">
                        {msg}
                      </p>
                    ))}
                  </div>
                ) : (
                  <p className="text-muted-foreground">No JavaScript error text was captured for this run.</p>
                )}

                <div className="grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                  <p>Status: {selectedRun.status}</p>
                  <p>HTTP status: {selectedRun.http_status ?? "-"}</p>
                  <p>JS errors: {selectedRun.js_errors_count}</p>
                  <p>Failed requests: {selectedRun.failed_requests_count}</p>
                </div>

                <div className="space-y-2 rounded-md border border-border/60 p-3">
                  <p className="font-medium">Suggested Checks</p>
                  {buildRunSuggestions(selectedRun).map((tip, idx) => (
                    <p key={`${selectedRun.id}-tip-${idx}`} className="text-xs text-muted-foreground">
                      {idx + 1}. {tip}
                    </p>
                  ))}
                </div>

                <div>
                  {screenshotByRunId.get(selectedRun.id) ? (
                    <Button type="button" variant="outline" size="sm" onClick={() => setActiveTab("screenshots")}>
                      Open Screenshot Tab
                    </Button>
                  ) : (
                    <p className="text-xs text-muted-foreground">No screenshot linked to this run.</p>
                  )}
                </div>
              </>
            ) : null}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function VaptReportPanel({
  report,
  isError,
  errorMessage,
}: {
  report: ExperienceMonitoringTechnicalReport | undefined;
  isError: boolean;
  errorMessage: string;
}) {
  if (isError) {
    return (
      <Card className="border-border/60">
        <CardContent className="pt-6">
          <p className="text-sm text-destructive">{errorMessage}</p>
        </CardContent>
      </Card>
    );
  }

  if (!report) {
    return (
      <Card className="border-border/60">
        <CardContent className="pt-6">
          <p className="text-sm text-muted-foreground">Loading report...</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-4">
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-sm">Severity</CardTitle>
          </CardHeader>
          <CardContent>
            <Badge variant={report.severity === "S0" || report.severity === "S1" ? "destructive" : report.severity === "S2" ? "warning" : "secondary"}>
              {report.severity}
            </Badge>
          </CardContent>
        </Card>
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-sm">Risk Score</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">{report.risk_score}</p>
          </CardContent>
        </Card>
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-sm">Success Rate</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">{report.journey.success_rate_pct ?? "-"}%</p>
          </CardContent>
        </Card>
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-sm">Samples</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-2xl font-semibold">{report.sample_count}</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-base">Technical Diagnostics</CardTitle>
            <CardDescription>DNS, TLS, server, and speed baselines used for VAPT-style checks.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <p>DNS Host: {report.network.dns?.host ?? "-"}</p>
            <p>Resolver(s): {(report.network.dns?.resolver_nameservers ?? []).join(", ") || "-"}</p>
            <p>DNS Lookup: {report.network.dns?.lookup_latency_ms ?? "-"} ms</p>
            <p>TLS Handshake: {report.network.tls?.handshake_ms ?? "-"} ms</p>
            <p>TLS Protocol: {report.network.tls?.protocol ?? "-"}</p>
            <p>Certificate Days Remaining: {report.network.tls?.certificate_days_remaining ?? "-"}</p>
            <p>Server Header: {report.network.security_headers.server ?? "-"}</p>
            <p>Security Header Gaps: {report.network.security_headers.missing_critical.join(", ") || "None"}</p>
            <p>Latest Speedtest Total: {report.network.latest_speedtest?.total_time_ms ?? "-"} ms</p>
            <p>Latest Speedtest TTFB: {report.network.latest_speedtest?.ttfb_ms ?? "-"} ms</p>
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-base">Findings and Suggestions</CardTitle>
            <CardDescription>Prioritized issues with direct recommended actions.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            {report.findings.map((finding, idx) => (
              <div key={`finding-${idx}`} className="rounded-md border border-border/60 p-2">
                <p className="font-medium">[{finding.severity}] {finding.title}</p>
                <p className="text-muted-foreground">{finding.detail}</p>
              </div>
            ))}
            <div className="space-y-1 rounded-md border border-border/60 p-2">
              <p className="font-medium">Suggested Actions</p>
              {report.suggestions.map((suggestion, idx) => (
                <p key={`suggestion-${idx}`} className="text-muted-foreground">{idx + 1}. {suggestion}</p>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
