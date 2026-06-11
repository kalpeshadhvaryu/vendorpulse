"use client";

import { useMemo, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { Download, Play } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { getApiErrorMessage } from "@/lib/api/errors";
import { runUrlChecker } from "@/lib/api/vapt-web-health";
import type { UrlCheckerReport } from "@/lib/api/types";

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

export default function UrlCheckerPage() {
  const [targetUrl, setTargetUrl] = useState("");
  const [maxLinks, setMaxLinks] = useState("200");
  const [report, setReport] = useState<UrlCheckerReport | null>(null);

  const mutation = useMutation({
    mutationFn: runUrlChecker,
    onSuccess: (data) => {
      setReport(data);
      toast.success("URL report generated");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const summary = report?.summary;

  const hasData = useMemo(() => Boolean(report && report.links.length > 0), [report]);

  return (
    <div className="mx-auto flex w-full max-w-7xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">URL Checker</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Enter a target web URL to crawl discovered anchors and evaluate link health with latency.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Run scan</CardTitle>
          <CardDescription>
            Checks internal and external links from the provided URL and marks 2xx links as active.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="rounded-lg border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
            <p className="font-medium text-foreground">Safety note</p>
            <p className="mt-1">
              URL Checker runs with a maximum of 5 parallel requests and browser-like headers to reduce
              rate-limiting and firewall blocks. Use this only on domains you own or are authorized to test.
            </p>
          </div>
          <div className="grid gap-3 md:grid-cols-[1fr_160px_auto]">
            <Input
              placeholder="https://example.com"
              value={targetUrl}
              onChange={(e) => setTargetUrl(e.target.value)}
            />
            <Input
              type="number"
              min={1}
              max={300}
              value={maxLinks}
              onChange={(e) => setMaxLinks(e.target.value)}
            />
            <Button
              onClick={() => mutation.mutate({ target_url: targetUrl, max_links: Number(maxLinks) || 200 })}
              disabled={mutation.isPending || targetUrl.trim() === ""}
            >
              <Play className="size-4" />
              {mutation.isPending ? "Scanning..." : "Run URL Checker"}
            </Button>
          </div>
        </CardContent>
      </Card>

      {summary ? (
        <div className="grid gap-4 md:grid-cols-4">
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Total Links Found</CardDescription>
              <CardTitle className="text-2xl">{summary.total_links}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Active Links (2xx)</CardDescription>
              <CardTitle className="text-2xl">{summary.active_links}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Broken Links (4xx/5xx)</CardDescription>
              <CardTitle className="text-2xl">{summary.broken_links}</CardTitle>
            </CardHeader>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Average Response Time</CardDescription>
              <CardTitle className="text-2xl">{summary.average_response_time_ms} ms</CardTitle>
            </CardHeader>
          </Card>
        </div>
      ) : null}

      {report ? (
        <Card className="border-border/60">
          <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <CardTitle>Detailed Report</CardTitle>
              <CardDescription>
                Source URL to discovered links with status code, health state, and response time.
              </CardDescription>
            </div>
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() =>
                  downloadTextFile(
                    "url-checker-report.json",
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
                onClick={() => downloadTextFile("url-checker-report.csv", toCsv(report), "text/csv;charset=utf-8")}
              >
                <Download className="size-4" />
                Export CSV
              </Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {!hasData ? (
              <p className="text-sm text-muted-foreground">No links found for this URL.</p>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Source URL</TableHead>
                      <TableHead>Discovered Link</TableHead>
                      <TableHead>Status Code</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Response Time</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {report.links.map((row, index) => (
                      <TableRow key={`${row.discovered_link}-${index}`}>
                        <TableCell className="max-w-[260px] truncate text-xs text-muted-foreground">
                          {row.source_url}
                        </TableCell>
                        <TableCell className="max-w-[340px] truncate text-xs">
                          {row.discovered_link}
                        </TableCell>
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
