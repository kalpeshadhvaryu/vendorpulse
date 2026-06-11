"use client";

import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { AlertTriangle, CheckCircle2, Download, PlayCircle, ShieldAlert } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { getApiErrorMessage } from "@/lib/api/errors";
import { runOnPageSeoAudit } from "@/lib/api/marketing-seo";
import type { OnPageSeoAuditReport, SeoAuditItem } from "@/lib/api/types";

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
          Score legend: <strong className="text-foreground">80-100</strong> healthy, <strong className="text-foreground">50-79</strong>{" "}
          needs optimization, <strong className="text-foreground">0-49</strong> high priority fixes.
        </p>
      </div>
    </div>
  );
}

export default function OnPageSeoScorecardPage() {
  const [targetUrl, setTargetUrl] = useState("");
  const [report, setReport] = useState<OnPageSeoAuditReport | null>(null);

  const mutation = useMutation({
    mutationFn: runOnPageSeoAudit,
    onSuccess: (data) => {
      setReport(data);
      toast.success("SEO scorecard generated");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">On-Page SEO Scorecard</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Analyze title, meta description, headings, image alt usage, and Open Graph or Twitter social tags.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Run SEO audit</CardTitle>
          <CardDescription>Enter a full target URL to generate a structured SEO scorecard.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 md:grid-cols-[1fr_auto]">
            <Input
              placeholder="https://example.com/page"
              value={targetUrl}
              onChange={(event) => setTargetUrl(event.target.value)}
            />
            <Button
              type="button"
              disabled={mutation.isPending || targetUrl.trim() === ""}
              onClick={() => mutation.mutate({ target_url: targetUrl.trim() })}
            >
              <PlayCircle className="size-4" />
              {mutation.isPending ? "Auditing..." : "Run audit"}
            </Button>
          </div>
        </CardContent>
      </Card>

      {report ? (
        <Card className="border-border/60">
          <CardHeader>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <CardTitle>SEO Scorecard Dashboard</CardTitle>
                <CardDescription>Scanned: {report.target_url}</CardDescription>
              </div>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() =>
                    downloadTextFile(
                      "on-page-seo-audit-report.json",
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
                      "on-page-seo-audit-report.csv",
                      toSeoAuditCsv(report),
                      "text/csv;charset=utf-8",
                    )
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
                title="Passed Audits"
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
                title="Critical Fixes"
                icon={<ShieldAlert className="h-4 w-4 text-red-600" />}
                items={report.critical_fixes}
                defaultOpen
              />
            </div>

            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Detected Values</CardTitle>
                <CardDescription>Raw content found on the page for key on-page SEO elements.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 text-sm">
                <div className="space-y-1">
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Title Tag</p>
                  <p className="rounded-md border border-border/60 bg-muted/20 p-2 text-foreground">
                    {report.extracted_values.title ?? "Not found"}
                  </p>
                </div>

                <div className="space-y-1">
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Meta Description</p>
                  <p className="rounded-md border border-border/60 bg-muted/20 p-2 text-foreground">
                    {report.extracted_values.meta_description ?? "Not found"}
                  </p>
                </div>

                <div className="grid gap-3 md:grid-cols-3">
                  {(["h1", "h2", "h3"] as const).map((key) => (
                    <div key={key} className="space-y-1">
                      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{key.toUpperCase()} Tags</p>
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

                <div className="space-y-1">
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Open Graph & Twitter Tags</p>
                  <div className="grid gap-2 md:grid-cols-2">
                    {Object.entries(report.extracted_values.open_graph).map(([key, value]) => (
                      <div key={key} className="rounded-md border border-border/60 bg-muted/20 p-2 text-xs">
                        <p className="font-medium text-foreground">{key}</p>
                        <p className="mt-1 text-muted-foreground break-all">{value ?? "Not found"}</p>
                      </div>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>

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
