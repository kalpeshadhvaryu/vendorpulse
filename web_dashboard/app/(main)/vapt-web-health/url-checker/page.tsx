"use client";

import { useMemo, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { Download, Play } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { getApiErrorMessage } from "@/lib/api/errors";
import { runUrlChecker } from "@/lib/api/vapt-web-health";
import type { UrlCheckerCrawlMeta, UrlCheckerReport } from "@/lib/api/types";

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

function toCsv(report: UrlCheckerReport): string {
  const headers = [
    "source_url",
    "discovered_link",
    "status_code",
    "status",
    "response_time_ms",
    "link_type",
    "error",
  ];

  const escapeValue = (value: string | number | null): string => {
    if (value === null) return "";
    const raw = String(value);
    if (raw.includes(",") || raw.includes("\n") || raw.includes('"')) {
      return `"${raw.replaceAll('"', '""')}"`;
    }
    return raw;
  };

  const rows = report.links.map((row) =>
    [
      row.source_url,
      row.discovered_link,
      row.status_code,
      row.status,
      row.response_time_ms,
      row.link_type,
      row.error,
    ]
      .map(escapeValue)
      .join(","),
  );

  return [headers.join(","), ...rows].join("\n");
}

function crawlStoppedMessage(crawl: UrlCheckerCrawlMeta): string | null {
  switch (crawl.stopped_reason) {
    case "max_pages":
      return `Stopped after reaching the ${crawl.max_pages} page limit.`;
    case "max_total_links":
      return `Stopped after collecting ${crawl.max_total_links} links.`;
    case "timeout":
      return "Stopped after the 10 minute crawl timeout.";
    case "completed":
      return crawl.unreachable_pages > 0
        ? `${crawl.unreachable_pages} page(s) could not be loaded during the crawl.`
        : null;
    default:
      return null;
  }
}

export default function UrlCheckerPage() {
  const [activeTab, setActiveTab] = useState<"quick" | "audit">("quick");

  const [quickTargetUrl, setQuickTargetUrl] = useState("");
  const [maxLinks, setMaxLinks] = useState("200");

  const [auditTargetUrl, setAuditTargetUrl] = useState("");
  const [maxPages, setMaxPages] = useState("50");
  const [maxDepth, setMaxDepth] = useState("3");
  const [maxTotalLinks, setMaxTotalLinks] = useState("500");
  const [authorized, setAuthorized] = useState(false);

  const [report, setReport] = useState<UrlCheckerReport | null>(null);

  const mutation = useMutation({
    mutationFn: runUrlChecker,
    onSuccess: (data) => {
      setReport(data);
      toast.success(data.mode === "site_crawl" ? "Website audit complete" : "Link report generated");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const summary = report?.summary;
  const crawl = report?.crawl;
  const hasData = useMemo(() => Boolean(report && report.links.length > 0), [report]);
  const crawlNotice = crawl ? crawlStoppedMessage(crawl) : null;

  const runQuickScan = () => {
    if (!quickTargetUrl.trim()) {
      toast.error("Target URL is required");
      return;
    }
    mutation.mutate({
      target_url: quickTargetUrl.trim(),
      mode: "quick",
      max_links: Number(maxLinks) || 200,
    });
  };

  const runWebsiteAudit = () => {
    if (!auditTargetUrl.trim()) {
      toast.error("Start URL is required");
      return;
    }
    if (!authorized) {
      toast.error("Confirm you are authorized to crawl this domain");
      return;
    }
    mutation.mutate({
      target_url: auditTargetUrl.trim(),
      mode: "site_crawl",
      max_pages: Number(maxPages) || 50,
      max_depth: Number(maxDepth) || 3,
      max_total_links: Number(maxTotalLinks) || 500,
      authorized: true,
    });
  };

  return (
    <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Link/Website Auditor</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Check links on a single page or crawl internal pages across your domain with bounded limits.
        </p>
      </div>

      <Tabs
        value={activeTab}
        onValueChange={(value) => {
          setActiveTab(value as "quick" | "audit");
          setReport(null);
        }}
      >
        <TabsList>
          <TabsTrigger value="quick">Quick Links</TabsTrigger>
          <TabsTrigger value="audit">Website Audit</TabsTrigger>
        </TabsList>

        <TabsContent value="quick" className="mt-4 space-y-4">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Quick Links</CardTitle>
              <CardDescription>
                Load one page, collect discovered anchors, and check internal and external link health.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-lg border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
                <p className="font-medium text-foreground">Safety note</p>
                <p className="mt-1">
                  Runs with a maximum of 5 parallel requests and browser-like headers. Use only on pages you own or are
                  authorized to test.
                </p>
              </div>
              <div className="grid gap-3 md:grid-cols-[1fr_160px_auto]">
                <div className="grid gap-2">
                  <Label htmlFor="quick-target-url">Target URL</Label>
                  <Input
                    id="quick-target-url"
                    placeholder="https://example.com/page"
                    value={quickTargetUrl}
                    onChange={(e) => setQuickTargetUrl(e.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="quick-max-links">Max links</Label>
                  <Input
                    id="quick-max-links"
                    type="number"
                    min={1}
                    max={300}
                    value={maxLinks}
                    onChange={(e) => setMaxLinks(e.target.value)}
                  />
                </div>
                <div className="flex items-end">
                  <Button
                    onClick={runQuickScan}
                    disabled={mutation.isPending || quickTargetUrl.trim() === ""}
                    className="w-full md:w-auto"
                  >
                    <Play className="size-4" />
                    {mutation.isPending && activeTab === "quick" ? "Scanning..." : "Run Quick Scan"}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="audit" className="mt-4 space-y-4">
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Website Audit</CardTitle>
              <CardDescription>
                Crawl internal pages on the same domain starting from your URL. External links are checked but not
                followed.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-lg border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
                <p className="font-medium text-foreground">Safety note</p>
                <p className="mt-1">
                  Site crawls may send many requests and can take several minutes. Start with low page limits. Runs are
                  capped at 5 parallel link checks and a 10 minute total timeout.
                </p>
              </div>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2 md:col-span-2">
                  <Label htmlFor="audit-target-url">Start URL</Label>
                  <Input
                    id="audit-target-url"
                    placeholder="https://example.com"
                    value={auditTargetUrl}
                    onChange={(e) => setAuditTargetUrl(e.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="audit-max-pages">Max pages</Label>
                  <Input
                    id="audit-max-pages"
                    type="number"
                    min={1}
                    max={500}
                    value={maxPages}
                    onChange={(e) => setMaxPages(e.target.value)}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="audit-max-depth">Max depth</Label>
                  <Input
                    id="audit-max-depth"
                    type="number"
                    min={0}
                    max={10}
                    value={maxDepth}
                    onChange={(e) => setMaxDepth(e.target.value)}
                  />
                </div>
                <div className="grid gap-2 md:col-span-2">
                  <Label htmlFor="audit-max-total-links">Max total links</Label>
                  <Input
                    id="audit-max-total-links"
                    type="number"
                    min={1}
                    max={2000}
                    value={maxTotalLinks}
                    onChange={(e) => setMaxTotalLinks(e.target.value)}
                  />
                </div>
              </div>
              <label className="flex items-start gap-2 text-sm text-muted-foreground">
                <input
                  type="checkbox"
                  className="mt-0.5 size-4 rounded border-input"
                  checked={authorized}
                  onChange={(e) => setAuthorized(e.target.checked)}
                />
                <span>I confirm I own this domain or have permission to crawl it.</span>
              </label>
              <Button
                onClick={runWebsiteAudit}
                disabled={mutation.isPending || auditTargetUrl.trim() === "" || !authorized}
              >
                <Play className="size-4" />
                {mutation.isPending && activeTab === "audit" ? "Crawling site..." : "Run Website Audit"}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {summary ? (
        <div className={`grid gap-4 ${crawl ? "md:grid-cols-5" : "md:grid-cols-4"}`}>
          {crawl ? (
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Pages crawled</CardDescription>
                <CardTitle className="text-2xl">{crawl.pages_crawled}</CardTitle>
              </CardHeader>
            </Card>
          ) : null}
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Total links found</CardDescription>
              <CardTitle className="text-2xl">{summary.total_links}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Active links (2xx)</CardDescription>
              <CardTitle className="text-2xl">{summary.active_links}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Broken links (4xx/5xx)</CardDescription>
              <CardTitle className="text-2xl">{summary.broken_links}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Average response time</CardDescription>
              <CardTitle className="text-2xl">{summary.average_response_time_ms} ms</CardTitle>
            </CardHeader>
          </Card>
        </div>
      ) : null}

      {crawlNotice ? (
        <p className="text-sm text-muted-foreground">{crawlNotice}</p>
      ) : null}

      {report ? (
        <Card className="border-border/60">
          <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <CardTitle>Detailed report</CardTitle>
              <CardDescription>
                Source page to discovered links with status code, health state, and response time.
              </CardDescription>
            </div>
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() =>
                  downloadTextFile(
                    report.mode === "site_crawl" ? "website-audit-report.json" : "quick-links-report.json",
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
                  downloadTextFile(
                    report.mode === "site_crawl" ? "website-audit-report.csv" : "quick-links-report.csv",
                    toCsv(report),
                    "text/csv;charset=utf-8",
                  )
                }
              >
                <Download className="size-4" />
                Export CSV
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {!hasData ? (
              <p className="text-sm text-muted-foreground">No links found for this scan.</p>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Source URL</TableHead>
                      <TableHead>Discovered link</TableHead>
                      <TableHead>Status code</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Response time</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {report.links.map((row, index) => (
                      <TableRow key={`${row.discovered_link}-${index}`}>
                        <TableCell className="max-w-[260px] truncate text-xs text-muted-foreground">
                          {row.source_url}
                        </TableCell>
                        <TableCell className="max-w-[340px] truncate text-xs">{row.discovered_link}</TableCell>
                        <TableCell>{row.status_code ?? "—"}</TableCell>
                        <TableCell>{row.status}</TableCell>
                        <TableCell>{row.response_time_ms != null ? `${row.response_time_ms} ms` : "—"}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}

            <details className="rounded-lg border border-border/60 p-3">
              <summary className="cursor-pointer text-sm font-medium">View JSON payload</summary>
              <pre className="mt-2 max-h-80 overflow-auto rounded bg-muted p-3 text-xs">
                {JSON.stringify(report, null, 2)}
              </pre>
            </details>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
