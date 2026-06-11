"use client";

import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import Link from "next/link";
import { ArrowLeft, ShieldCheck } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { getApiErrorMessage } from "@/lib/api/errors";
import { runDnsCheck } from "@/lib/api/vapt-web-health";
import type { DnsCheckItem, DnsCheckReport } from "@/lib/api/types";

function splitEvidenceLine(line: string): string[] {
  if (!line.includes(";")) {
    return [line];
  }

  return line
    .split(";")
    .map((s) => s.trim())
    .filter(Boolean);
}

function statusBadge(item: DnsCheckItem) {
  if (item.status === "fail") {
    return <Badge variant="destructive">Fail</Badge>;
  }

  if (item.status === "warning") {
    return <Badge variant="warning">Warning</Badge>;
  }

  return <Badge variant="secondary">Pass</Badge>;
}

export default function DnsCheckPage() {
  const [target, setTarget] = useState("");
  const [report, setReport] = useState<DnsCheckReport | null>(null);
  const [findingSearch, setFindingSearch] = useState("");
  const [findingStatus, setFindingStatus] = useState<"all" | "pass" | "warning" | "fail">("all");

  const mutation = useMutation({
    mutationFn: runDnsCheck,
    onSuccess: (data) => {
      setReport(data);
      toast.success("DNS check completed");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const visibleFindings = (report?.checks ?? []).filter((item) => {
    const statusMatch = findingStatus === "all" || item.status === findingStatus;
    const q = findingSearch.trim().toLowerCase();
    if (!q) {
      return statusMatch;
    }

    const haystack = [item.title, item.explanation, item.suggestion, item.severity, ...item.evidence]
      .join(" ")
      .toLowerCase();

    return statusMatch && haystack.includes(q);
  });

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1 px-2" asChild>
          <Link href="/vapt-web-health">
            <ArrowLeft className="h-4 w-4" />
            VAPT &amp; Web Health
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">DNS Check</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Scan DNS records and policy posture with actionable explanation and suggestions.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShieldCheck className="h-5 w-5" />
            Run DNS scan
          </CardTitle>
          <CardDescription>
            Enter a domain or URL. The scanner evaluates DNS resolution, record posture, latency, and mail security policy checks.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <form
            className="grid gap-3 md:grid-cols-[1fr_auto]"
            onSubmit={(e) => {
              e.preventDefault();
              if (target.trim() === "" || mutation.isPending) {
                return;
              }
              mutation.mutate({ target });
            }}
          >
            <Input
              placeholder="example.com or https://example.com"
              value={target}
              onChange={(e) => setTarget(e.target.value)}
            />
            <Button
              type="submit"
              disabled={mutation.isPending || target.trim() === ""}
            >
              {mutation.isPending ? "Scanning..." : "Run DNS Check"}
            </Button>
          </form>
          <p className="text-xs text-muted-foreground">
            Use only domains you own or are authorized to assess.
          </p>
        </CardContent>
      </Card>

      {report ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Total Checks</CardDescription>
                <CardTitle className="text-2xl">{report.summary.total_checks}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Passed</CardDescription>
                <CardTitle className="text-2xl">{report.summary.passed}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Warnings</CardDescription>
                <CardTitle className="text-2xl">{report.summary.warnings}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Failed</CardDescription>
                <CardTitle className="text-2xl">{report.summary.failed}</CardTitle>
              </CardHeader>
            </Card>
          </div>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Scan context</CardTitle>
              <CardDescription>Target and resolver context used during this scan.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p>Target: {report.target}</p>
              <p>Resolved Host: {report.host || "-"}</p>
              <p>Scanned At: {new Date(report.scanned_at).toLocaleString()}</p>
              <p>Resolvers: {report.resolver_nameservers.join(", ") || "-"}</p>
              <p>Parent Delegation NS: {report.records.parent_delegation_ns?.join(", ") || "-"}</p>
            </CardContent>
          </Card>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Advanced DNS Diagnostics</CardTitle>
              <CardDescription>Cross-resolver consensus and nameserver host resolution details.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div className="space-y-1 rounded-md border border-border/60 p-3">
                <p className="font-medium">Resolver Consensus</p>
                <p>Local A: {report.records.resolver_consensus?.local_a.join(", ") || "none"}</p>
                <p>Google A: {report.records.resolver_consensus?.google_a.join(", ") || "none"}</p>
                <p>Cloudflare A: {report.records.resolver_consensus?.cloudflare_a.join(", ") || "none"}</p>
                <p>Google Error: {report.records.resolver_consensus?.google_error || "none"}</p>
                <p>Cloudflare Error: {report.records.resolver_consensus?.cloudflare_error || "none"}</p>
              </div>

              <div className="space-y-1 rounded-md border border-border/60 p-3">
                <p className="font-medium">Nameserver Host Resolution</p>
                {report.records.nameserver_host_resolution && Object.keys(report.records.nameserver_host_resolution).length > 0 ? (
                  Object.entries(report.records.nameserver_host_resolution).map(([ns, rows]) => (
                    <p key={ns}>
                      {ns}: A[{rows.a.join(", ") || "none"}] AAAA[{rows.aaaa.join(", ") || "none"}]
                    </p>
                  ))
                ) : (
                  <p>No nameserver host resolution data available.</p>
                )}
              </div>
            </CardContent>
          </Card>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Findings</CardTitle>
              <CardDescription>
                Every check includes status, explanation, and recommendation.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid gap-3 md:grid-cols-[1fr_auto]">
                <Input
                  placeholder="Search findings, suggestion, severity, evidence..."
                  value={findingSearch}
                  onChange={(e) => setFindingSearch(e.target.value)}
                />
                <select
                  className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                  value={findingStatus}
                  onChange={(e) => setFindingStatus(e.target.value as "all" | "pass" | "warning" | "fail")}
                >
                  <option value="all">All statuses</option>
                  <option value="pass">Pass</option>
                  <option value="warning">Warning</option>
                  <option value="fail">Fail</option>
                </select>
              </div>

              {visibleFindings.length === 0 ? (
                <p className="text-sm text-muted-foreground">No findings matched your search/filter.</p>
              ) : null}

              {visibleFindings.map((item) => (
                <div key={item.key} className="rounded-md border border-border/60 p-3">
                  <div className="mb-1 flex flex-wrap items-center gap-2">
                    <p className="font-medium">{item.title}</p>
                    {statusBadge(item)}
                    <Badge variant="outline">{item.severity}</Badge>
                  </div>
                  <p className="text-sm text-muted-foreground">{item.explanation}</p>
                  <p className="mt-1 text-sm">
                    <span className="font-medium">Suggestion:</span> {item.suggestion}
                  </p>
                  {item.evidence.length > 0 ? (
                    <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                      {item.evidence.flatMap((row) => splitEvidenceLine(row)).map((row, idx) => (
                        <p key={`${item.key}-${idx}`}>- {row}</p>
                      ))}
                    </div>
                  ) : null}
                </div>
              ))}
            </CardContent>
          </Card>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>API JSON Output</CardTitle>
              <CardDescription>Raw DNS payload for copy/paste, automation, or external tooling.</CardDescription>
            </CardHeader>
            <CardContent>
              <pre className="max-h-96 overflow-auto rounded bg-muted p-3 text-xs">
                {JSON.stringify(report, null, 2)}
              </pre>
            </CardContent>
          </Card>

        </>
      ) : null}
    </div>
  );
}
