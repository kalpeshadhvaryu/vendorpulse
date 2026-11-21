"use client";

import { useState } from "react";
import Link from "next/link";
import { useMutation } from "@tanstack/react-query";
import { ArrowLeft, Download, LockKeyhole, ShieldAlert } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { getApiErrorMessage } from "@/lib/api/errors";
import { runSecurityAudit } from "@/lib/api/vapt-web-health";
import type { SecurityAuditFinding, SecurityAuditReport } from "@/lib/api/types";

function statusBadge(status: string) {
  if (status === "fail") return <Badge variant="destructive">Fail</Badge>;
  if (status === "warning") return <Badge variant="warning">Warning</Badge>;
  return <Badge variant="secondary">Pass</Badge>;
}

function categoryLabel(category: string) {
  return category.replaceAll("_", " ");
}

export default function SecurityAuditPage() {
  const [targetUrl, setTargetUrl] = useState("");
  const [depth, setDepth] = useState<"standard" | "deep">("standard");
  const [authorized, setAuthorized] = useState(false);
  const [report, setReport] = useState<SecurityAuditReport | null>(null);
  const [filter, setFilter] = useState<"all" | "fail" | "warning" | "pass">("all");

  const mutation = useMutation({
    mutationFn: runSecurityAudit,
    onSuccess: (data) => {
      setReport(data);
      toast.success("Security audit completed");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const findings = (report?.findings ?? []).filter((item) =>
    filter === "all" ? true : item.status === filter,
  );

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1 px-2" asChild>
          <Link href="/vapt-web-health">
            <ArrowLeft className="h-4 w-4" />
            VAPT &amp; Web Health
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">Website Security Audit</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Phase-B checks for security misconfiguration, session/cookie hardening, CSRF form defenses,
          sensitive path exposure, and optional safe injection heuristics.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <LockKeyhole className="h-5 w-5" />
            Run audit
          </CardTitle>
          <CardDescription>
            Standard maps configuration and surfaces. Deep adds safe reflected-XSS / SQL-error probes (no
            destructive payloads).
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <form
            className="grid gap-3"
            onSubmit={(e) => {
              e.preventDefault();
              if (!authorized) {
                toast.error("Confirm you are authorized to security-test this domain");
                return;
              }
              if (targetUrl.trim() === "" || mutation.isPending) return;
              mutation.mutate({
                target_url: targetUrl.trim(),
                authorized: true,
                depth,
              });
            }}
          >
            <Input
              placeholder="https://example.com"
              value={targetUrl}
              onChange={(e) => setTargetUrl(e.target.value)}
            />
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                variant={depth === "standard" ? "default" : "outline"}
                onClick={() => setDepth("standard")}
              >
                Standard
              </Button>
              <Button
                type="button"
                size="sm"
                variant={depth === "deep" ? "default" : "outline"}
                onClick={() => setDepth("deep")}
              >
                Deep
              </Button>
            </div>
            <label className="flex items-start gap-2 text-sm">
              <input
                type="checkbox"
                className="mt-1"
                checked={authorized}
                onChange={(e) => setAuthorized(e.target.checked)}
              />
              <span>
                I own this site or am explicitly authorized to run security checks on it.
              </span>
            </label>
            <div>
              <Button type="submit" disabled={mutation.isPending || targetUrl.trim() === "" || !authorized}>
                {mutation.isPending ? "Auditing..." : "Run Security Audit"}
              </Button>
            </div>
          </form>
          <p className="text-xs text-muted-foreground">
            This is not a full manual pentest. It does not brute-force passwords, exploit RCE, or test
            complex business-logic authorization flaws.
          </p>
        </CardContent>
      </Card>

      {report ? (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap gap-2">
              {(["all", "fail", "warning", "pass"] as const).map((value) => (
                <Button
                  key={value}
                  type="button"
                  size="sm"
                  variant={filter === value ? "default" : "outline"}
                  onClick={() => setFilter(value)}
                >
                  {value}
                </Button>
              ))}
            </div>
            <Button
              type="button"
              onClick={() => {
                void import("@/lib/api/reports")
                  .then(({ downloadReportPdf }) =>
                    downloadReportPdf("security-audit", report, {
                      meta: { target: report.final_url || report.target_url },
                      fileName: `security-audit-${report.host || "report"}.pdf`,
                    }),
                  )
                  .then(() => toast.success("PDF downloaded"))
                  .catch((error) => toast.error(getApiErrorMessage(error)));
              }}
            >
              <Download className="mr-2 h-4 w-4" />
              Download PDF
            </Button>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Severity</CardDescription>
                <CardTitle className="text-2xl">{report.severity}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Risk score</CardDescription>
                <CardTitle className="text-2xl">{report.risk_score}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Failed</CardDescription>
                <CardTitle className="text-2xl">{report.summary.failed}</CardTitle>
              </CardHeader>
            </Card>
            <Card className="border-border/60">
              <CardHeader className="pb-2">
                <CardDescription>Warnings</CardDescription>
                <CardTitle className="text-2xl">{report.summary.warnings}</CardTitle>
              </CardHeader>
            </Card>
          </div>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-base">
                <ShieldAlert className="h-4 w-4" />
                Category coverage
              </CardTitle>
              <CardDescription>
                Mapped to common VAPT themes: misconfiguration, auth/session, CSRF, exposure, injection,
                XSS surface.
              </CardDescription>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="py-2 pr-3">Category</th>
                    <th className="py-2 pr-3">Pass</th>
                    <th className="py-2 pr-3">Warning</th>
                    <th className="py-2">Fail</th>
                  </tr>
                </thead>
                <tbody>
                  {Object.entries(report.categories).map(([name, counts]) => (
                    <tr key={name} className="border-t border-border/60">
                      <td className="py-2 pr-3 capitalize">{categoryLabel(name)}</td>
                      <td className="py-2 pr-3">{counts.pass}</td>
                      <td className="py-2 pr-3">{counts.warning}</td>
                      <td className="py-2">{counts.fail}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle className="text-base">Findings</CardTitle>
              <CardDescription>
                {findings.length} shown · target {report.final_url || report.target_url}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {findings.length === 0 ? (
                <p className="text-sm text-muted-foreground">No findings for this filter.</p>
              ) : (
                findings.map((item: SecurityAuditFinding, idx) => (
                  <div key={`${item.title}-${idx}`} className="rounded-lg border border-border/60 p-3">
                    <div className="flex flex-wrap items-center gap-2">
                      {statusBadge(item.status)}
                      <Badge variant="outline">{item.severity}</Badge>
                      <span className="text-xs capitalize text-muted-foreground">
                        {categoryLabel(item.category)}
                      </span>
                    </div>
                    <p className="mt-2 font-medium">{item.title}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{item.explanation}</p>
                    <p className="mt-2 text-sm">
                      <span className="font-medium">Suggestion:</span> {item.suggestion}
                    </p>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          {report.scope_note ? (
            <p className="text-xs text-muted-foreground">{report.scope_note}</p>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
