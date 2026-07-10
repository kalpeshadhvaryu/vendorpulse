"use client";

import { useMemo, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { AlertTriangle, CheckCircle2, Download, PlayCircle, ShieldAlert } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { getApiErrorMessage } from "@/lib/api/errors";
import { runOnPageSeoAudit } from "@/lib/api/marketing-seo";
import type { OnPageSeoAuditReport, SeoAuditItem, SeoCrawlMeta } from "@/lib/api/types";

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

function toSeoAuditCsv(report: OnPageSeoAuditReport): string {
  if (report.mode === "site_crawl" && report.pages) {
    const headers = ["url", "overall_score", "passed", "warnings", "critical", "top_critical", "unreachable"];
    const escapeValue = (value: string | number | boolean | null): string => {
      if (value === null) return "";
      const raw = String(value);
      if (raw.includes(",") || raw.includes("\n") || raw.includes('"')) {
        return `"${raw.replaceAll('"', '""')}"`;
      }
      return raw;
    };
    const rows = report.pages.map((page) =>
      [
        page.url,
        page.overall_score,
        page.totals.passed,
        page.totals.warnings,
        page.totals.critical,
        page.top_critical,
        page.unreachable,
      ]
        .map(escapeValue)
        .join(","),
    );
    return [headers.join(","), ...rows].join("\n");
  }

  const headers = ["section", "key", "title", "message", "suggestion"];
  const rows: Array<[string, string, string, string, string]> = [];
  report.passed_audits.forEach((item) => {
    rows.push(["passed", item.key, item.title, item.message, item.suggestion ?? ""]);
  });
  report.warnings.forEach((item) => {
    rows.push(["warning", item.key, item.title, item.message, item.suggestion ?? ""]);
  });
  report.critical_fixes.forEach((item) => {
    rows.push(["critical", item.key, item.title, item.message, item.suggestion ?? ""]);
  });

  const escapeValue = (value: string): string => {
    if (value.includes(",") || value.includes("\n") || value.includes('"')) {
      return `"${value.replaceAll('"', '""')}"`;
    }
    return value;
  };

  const body = rows.map((row) => row.map(escapeValue).join(","));
  return [headers.join(","), ...body].join("\n");
}

function crawlStoppedMessage(crawl: SeoCrawlMeta): string | null {
  switch (crawl.stopped_reason) {
    case "max_pages":
      return `Stopped after reaching the ${crawl.max_pages} page limit.`;
    case "timeout":
      return "Stopped after the 15 minute crawl timeout.";
    case "completed":
      return crawl.unreachable_pages > 0
        ? `${crawl.unreachable_pages} page(s) could not be loaded during the crawl.`
        : null;
    default:
      return null;
  }
}

function AuditSection({
  title,
  icon,
  items,
  defaultOpen,
}: {
  title: string;
  icon: React.ReactNode;
  items: SeoAuditItem[];
  defaultOpen?: boolean;
}) {
  return (
    <details className="rounded-lg border border-border/60 p-3" open={defaultOpen}>
      <summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium">
        <span className="flex items-center gap-2">
          {icon}
          {title}
        </span>
        <span className="rounded bg-muted px-2 py-0.5 text-xs">{items.length}</span>
      </summary>

      <div className="mt-3 space-y-2">
        {items.length === 0 ? (
          <p className="text-xs text-muted-foreground">No items in this section.</p>
        ) : (
          items.map((item) => (
            <div key={item.key} className="rounded-md border border-border/60 bg-muted/20 p-3 text-sm">
              <p className="font-medium text-foreground">{item.title}</p>
              <p className="mt-1 text-xs text-muted-foreground">{item.message}</p>
              {item.suggestion ? <p className="mt-2 text-xs text-foreground/90">Fix: {item.suggestion}</p> : null}
            </div>
          ))
        )}
      </div>
    </details>
  );
}

function ScoreRing({ score }: { score: number }) {
  const safeScore = Math.max(0, Math.min(100, score));

  return (
    <div className="flex items-center gap-5">
      <div
        className="relative h-28 w-28 rounded-full"
        style={{
          background: `conic-gradient(hsl(var(--primary)) ${safeScore * 3.6}deg, hsl(var(--muted)) 0deg)`,
        }}
      >
        <div className="absolute inset-[7px] flex items-center justify-center rounded-full bg-background">
          <div className="text-center">
            <p className="text-2xl font-semibold leading-none">{safeScore}</p>
            <p className="mt-1 text-[10px] uppercase tracking-wide text-muted-foreground">SEO Score</p>
          </div>
        </div>
      </div>

      <div className="space-y-1 text-sm text-muted-foreground">
        <p>
          Score legend: <strong className="text-foreground">80-100</strong> healthy,{" "}
          <strong className="text-foreground">50-79</strong> needs optimization,{" "}
          <strong className="text-foreground">0-49</strong> high priority fixes.
        </p>
      </div>
    </div>
  );
}

function SerpPreview({ report }: { report: OnPageSeoAuditReport }) {
  const title = report.extracted_values.title ?? report.target_url;
  const description = report.extracted_values.meta_description ?? "No meta description found for this page.";
  const displayUrl = report.extracted_values.final_url ?? report.target_url;

  return (
    <Card className="border-border/60">
      <CardHeader className="pb-2">
        <CardTitle className="text-base">SERP preview</CardTitle>
        <CardDescription>Approximate Google-style snippet from title and meta description.</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="rounded-lg border border-border/60 bg-background p-4">
          <p className="truncate text-sm text-[#1a0dab]">{title}</p>
          <p className="mt-1 truncate text-xs text-[#006621]">{displayUrl}</p>
          <p className="mt-2 line-clamp-2 text-sm text-[#545454]">{description}</p>
        </div>
      </CardContent>
    </Card>
  );
}

function SharePreview({ report }: { report: OnPageSeoAuditReport }) {
  const og = report.extracted_values.open_graph;
  const title = og["og:title"] ?? report.extracted_values.title ?? "No Open Graph title";
  const description = og["og:description"] ?? report.extracted_values.meta_description ?? "No Open Graph description";
  const image = og["og:image"];

  return (
    <Card className="border-border/60">
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Share preview</CardTitle>
        <CardDescription>Open Graph preview for social link cards.</CardDescription>
      </CardHeader>
      <CardContent>
        <div className="overflow-hidden rounded-lg border border-border/60 bg-muted/20">
          {image ? (
            <div className="aspect-[1.91/1] w-full bg-muted">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={image} alt="" className="h-full w-full object-cover" />
            </div>
          ) : (
            <div className="flex aspect-[1.91/1] items-center justify-center bg-muted text-xs text-muted-foreground">
              No og:image found
            </div>
          )}
          <div className="space-y-1 p-3">
            <p className="line-clamp-1 text-sm font-medium text-foreground">{title}</p>
            <p className="line-clamp-2 text-xs text-muted-foreground">{description}</p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function QuickSeoResults({ report }: { report: OnPageSeoAuditReport }) {
  return (
    <Card className="border-border/60">
      <CardHeader>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle>Quick SEO results</CardTitle>
            <CardDescription>Scanned: {report.target_url}</CardDescription>
          </div>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() =>
                downloadTextFile(
                  "quick-seo-audit.json",
                  JSON.stringify(report, null, 2),
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
              onClick={() =>
                downloadTextFile("quick-seo-audit.csv", toSeoAuditCsv(report), "text/csv;charset=utf-8")
              }
            >
              <Download className="size-4" />
              Export CSV
            </Button>
          </div>
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        <ScoreRing score={report.overall_score} />

        <div className="grid gap-4 lg:grid-cols-2">
          <SerpPreview report={report} />
          <SharePreview report={report} />
        </div>

        <div className="grid gap-3 sm:grid-cols-3">
          <div className="rounded-lg border border-border/60 bg-muted/20 p-3 text-sm">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">Passed</p>
            <p className="mt-1 text-2xl font-semibold text-foreground">{report.totals.passed}</p>
          </div>
          <div className="rounded-lg border border-border/60 bg-amber-500/5 p-3 text-sm">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">Warnings</p>
            <p className="mt-1 text-2xl font-semibold text-amber-600">{report.totals.warnings}</p>
          </div>
          <div className="rounded-lg border border-border/60 bg-red-500/5 p-3 text-sm">
            <p className="text-xs uppercase tracking-wide text-muted-foreground">Critical fixes</p>
            <p className="mt-1 text-2xl font-semibold text-red-600">{report.totals.critical}</p>
          </div>
        </div>

        <div className="space-y-3">
          <AuditSection
            title="Passed audits"
            icon={<CheckCircle2 className="h-4 w-4 text-emerald-600" />}
            items={report.passed_audits}
          />
          <AuditSection
            title="Warnings"
            icon={<AlertTriangle className="h-4 w-4 text-amber-600" />}
            items={report.warnings}
            defaultOpen
          />
          <AuditSection
            title="Critical fixes"
            icon={<ShieldAlert className="h-4 w-4 text-red-600" />}
            items={report.critical_fixes}
            defaultOpen
          />
        </div>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-base">Detected values</CardTitle>
            <CardDescription>Raw content found on the page for key on-page SEO elements.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            <div className="grid gap-3 md:grid-cols-2">
              <div className="space-y-1">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Title tag</p>
                <p className="rounded-md border border-border/60 bg-muted/20 p-2 text-foreground">
                  {report.extracted_values.title ?? "Not found"}
                </p>
              </div>
              <div className="space-y-1">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Meta description</p>
                <p className="rounded-md border border-border/60 bg-muted/20 p-2 text-foreground">
                  {report.extracted_values.meta_description ?? "Not found"}
                </p>
              </div>
              <div className="space-y-1">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Canonical</p>
                <p className="rounded-md border border-border/60 bg-muted/20 p-2 text-foreground">
                  {report.extracted_values.canonical ?? "Not found"}
                </p>
              </div>
              <div className="space-y-1">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Robots</p>
                <p className="rounded-md border border-border/60 bg-muted/20 p-2 text-foreground">
                  {report.extracted_values.robots ?? "Not set"}
                </p>
              </div>
            </div>

            <div className="grid gap-3 md:grid-cols-3">
              {(["h1", "h2", "h3"] as const).map((key) => (
                <div key={key} className="space-y-1">
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    {key.toUpperCase()} tags
                  </p>
                  <div className="rounded-md border border-border/60 bg-muted/20 p-2">
                    {report.extracted_values.headings[key].length === 0 ? (
                      <p className="text-xs text-muted-foreground">None found</p>
                    ) : (
                      <ul className="space-y-1 text-xs text-foreground">
                        {report.extracted_values.headings[key].map((value, idx) => (
                          <li key={`${key}-${idx}`} className="rounded bg-background/80 px-2 py-1">
                            {value}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </CardContent>
    </Card>
  );
}

function SiteSeoResults({ report }: { report: OnPageSeoAuditReport }) {
  const crawlNotice = report.crawl ? crawlStoppedMessage(report.crawl) : null;
  const siteSummary = report.site_summary;

  return (
    <div className="space-y-4">
      {siteSummary ? (
        <div className="grid gap-4 md:grid-cols-5">
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Pages audited</CardDescription>
              <CardTitle className="text-2xl">{report.crawl?.pages_crawled ?? report.pages?.length ?? 0}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Average score</CardDescription>
              <CardTitle className="text-2xl">{siteSummary.average_score}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Lowest score</CardDescription>
              <CardTitle className="text-2xl">{siteSummary.lowest_score}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Critical issues</CardDescription>
              <CardTitle className="text-2xl">{siteSummary.total_critical}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Warnings</CardDescription>
              <CardTitle className="text-2xl">{siteSummary.total_warnings}</CardTitle>
            </CardHeader>
          </Card>
        </div>
      ) : null}

      {crawlNotice ? <p className="text-sm text-muted-foreground">{crawlNotice}</p> : null}

      {siteSummary?.lowest_url ? (
        <p className="text-sm text-muted-foreground">
          Lowest-scoring page: <span className="font-mono text-xs">{siteSummary.lowest_url}</span>
        </p>
      ) : null}

      <Card className="border-border/60">
        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle>Site SEO audit results</CardTitle>
            <CardDescription>Per-page scores and recurring issues across the crawl.</CardDescription>
          </div>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() =>
                downloadTextFile(
                  "site-seo-audit.json",
                  JSON.stringify(report, null, 2),
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
              onClick={() =>
                downloadTextFile("site-seo-audit.csv", toSeoAuditCsv(report), "text/csv;charset=utf-8")
              }
            >
              <Download className="size-4" />
              Export CSV
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          {report.issue_rollups && report.issue_rollups.length > 0 ? (
            <div className="space-y-3">
              <h3 className="text-sm font-medium">Recurring issues</h3>
              {report.issue_rollups.map((issue) => (
                <div key={issue.key} className="rounded-md border border-border/60 bg-muted/20 p-3 text-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-medium text-foreground">{issue.title}</p>
                    <span className="rounded bg-background px-2 py-0.5 text-xs text-muted-foreground">
                      {issue.page_count} page{issue.page_count === 1 ? "" : "s"}
                    </span>
                  </div>
                  <p className="mt-2 font-mono text-xs text-muted-foreground">{issue.urls.slice(0, 3).join(", ")}</p>
                </div>
              ))}
            </div>
          ) : null}

          {report.pages && report.pages.length > 0 ? (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Page URL</TableHead>
                    <TableHead>Score</TableHead>
                    <TableHead>Critical</TableHead>
                    <TableHead>Warnings</TableHead>
                    <TableHead>Top issue</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {report.pages.map((page) => (
                    <TableRow key={page.url}>
                      <TableCell className="max-w-[320px] truncate text-xs">{page.url}</TableCell>
                      <TableCell>{page.unreachable ? "—" : page.overall_score}</TableCell>
                      <TableCell>{page.totals.critical}</TableCell>
                      <TableCell>{page.totals.warnings}</TableCell>
                      <TableCell className="max-w-[220px] truncate text-xs text-muted-foreground">
                        {page.top_critical ?? (page.unreachable ? "Unreachable" : "—")}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">No pages were audited.</p>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

export default function OnPageSeoScorecardPage() {
  const [activeTab, setActiveTab] = useState<"quick" | "site">("quick");

  const [quickTargetUrl, setQuickTargetUrl] = useState("");
  const [quickAuthorized, setQuickAuthorized] = useState(false);
  const [siteTargetUrl, setSiteTargetUrl] = useState("");
  const [maxPages, setMaxPages] = useState("30");
  const [maxDepth, setMaxDepth] = useState("3");
  const [authorized, setAuthorized] = useState(false);

  const [report, setReport] = useState<OnPageSeoAuditReport | null>(null);

  const mutation = useMutation({
    mutationFn: runOnPageSeoAudit,
    onSuccess: (data) => {
      setReport(data);
      toast.success(data.mode === "site_crawl" ? "Site SEO audit complete" : "Quick SEO scorecard generated");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const isSiteReport = useMemo(() => report?.mode === "site_crawl", [report]);

  const runQuickAudit = () => {
    if (!quickTargetUrl.trim()) {
      toast.error("Target URL is required");
      return;
    }
    if (!quickAuthorized) {
      toast.error("Confirm you are authorized to crawl this domain");
      return;
    }
    mutation.mutate({
      target_url: quickTargetUrl.trim(),
      mode: "quick",
      authorized: true,
    });
  };

  const runSiteAudit = () => {
    if (!siteTargetUrl.trim()) {
      toast.error("Start URL is required");
      return;
    }
    if (!authorized) {
      toast.error("Confirm you are authorized to crawl this domain");
      return;
    }
    mutation.mutate({
      target_url: siteTargetUrl.trim(),
      mode: "site_crawl",
      max_pages: Number(maxPages) || 30,
      max_depth: Number(maxDepth) || 3,
      authorized: true,
    });
  };

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">On-Page SEO</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Analyze one page for search readiness or audit SEO signals across internal pages on your domain.
        </p>
      </div>

      <Tabs
        value={activeTab}
        onValueChange={(value) => {
          setActiveTab(value as "quick" | "site");
          setReport(null);
        }}
      >
        <TabsList>
          <TabsTrigger value="quick">Quick SEO</TabsTrigger>
          <TabsTrigger value="site">Site SEO Audit</TabsTrigger>
        </TabsList>

        <TabsContent value="quick" className="mt-4 space-y-4">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Quick SEO</CardTitle>
              <CardDescription>
                Check title, meta, headings, technical tags, content signals, and social metadata on a single page.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                <div className="grid gap-2">
                  <Label htmlFor="quick-target-url">Target URL</Label>
                  <Input
                    id="quick-target-url"
                    placeholder="https://example.com/page"
                    value={quickTargetUrl}
                    onChange={(event) => setQuickTargetUrl(event.target.value)}
                  />
                </div>
                <div className="flex items-end">
                  <Button
                    type="button"
                    disabled={mutation.isPending || quickTargetUrl.trim() === "" || !quickAuthorized}
                    onClick={runQuickAudit}
                    className="w-full md:w-auto"
                  >
                    <PlayCircle className="size-4" />
                    {mutation.isPending && activeTab === "quick" ? "Auditing..." : "Run Quick SEO"}
                  </Button>
                </div>
              </div>
              <label className="flex items-start gap-2 text-sm text-muted-foreground">
                <input
                  type="checkbox"
                  className="mt-0.5 size-4 rounded border-input"
                  checked={quickAuthorized}
                  onChange={(event) => setQuickAuthorized(event.target.checked)}
                />
                <span>I confirm I own this domain or have permission to crawl it.</span>
              </label>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="site" className="mt-4 space-y-4">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Site SEO Audit</CardTitle>
              <CardDescription>
                Crawl internal pages on the same domain and score each page for on-page SEO issues.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-lg border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
                <p className="font-medium text-foreground">Safety note</p>
                <p className="mt-1">
                  Site audits may take several minutes. Start with low page limits and only crawl domains you own or are
                  authorized to test.
                </p>
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2 md:col-span-2">
                  <Label htmlFor="site-target-url">Start URL</Label>
                  <Input
                    id="site-target-url"
                    placeholder="https://example.com"
                    value={siteTargetUrl}
                    onChange={(event) => setSiteTargetUrl(event.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="site-max-pages">Max pages</Label>
                  <Input
                    id="site-max-pages"
                    type="number"
                    min={1}
                    max={200}
                    value={maxPages}
                    onChange={(event) => setMaxPages(event.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="site-max-depth">Max depth</Label>
                  <Input
                    id="site-max-depth"
                    type="number"
                    min={0}
                    max={10}
                    value={maxDepth}
                    onChange={(event) => setMaxDepth(event.target.value)}
                  />
                </div>
              </div>
              <label className="flex items-start gap-2 text-sm text-muted-foreground">
                <input
                  type="checkbox"
                  className="mt-0.5 size-4 rounded border-input"
                  checked={authorized}
                  onChange={(event) => setAuthorized(event.target.checked)}
                />
                <span>I confirm I own this domain or have permission to crawl it.</span>
              </label>
              <Button
                type="button"
                disabled={mutation.isPending || siteTargetUrl.trim() === "" || !authorized}
                onClick={runSiteAudit}
              >
                <PlayCircle className="size-4" />
                {mutation.isPending && activeTab === "site" ? "Auditing site..." : "Run Site SEO Audit"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {report ? (
        isSiteReport ? <SiteSeoResults report={report} /> : <QuickSeoResults report={report} />
      ) : null}
    </div>
  );
}
