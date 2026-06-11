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
import { runPortChecker } from "@/lib/api/vapt-web-health";
import type { PortCheckerFinding, PortCheckerReport } from "@/lib/api/types";

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

function toPortsCsv(report: PortCheckerReport): string {
  const headers = ["port", "state", "latency_ms", "service_hint", "risk", "banner", "error"];

  const escapeValue = (value: string | number | null): string => {
    if (value === null) return "";
    const raw = String(value);
    if (raw.includes(",") || raw.includes("\n") || raw.includes('"')) {
      return `"${raw.replaceAll('"', '""')}"`;
    }
    return raw;
  };

  const rows = report.ports.map((row) =>
    [row.port, row.state, row.latency_ms, row.service_hint, row.risk, row.banner, row.error]
      .map((value) => escapeValue(value as string | number | null))
      .join(","),
  );

  return [headers.join(","), ...rows].join("\n");
}

function findingBadge(item: PortCheckerFinding) {
  if (item.status === "fail") {
    return <Badge variant="destructive">Fail</Badge>;
  }

  if (item.status === "warning") {
    return <Badge variant="warning">Warning</Badge>;
  }

  return <Badge variant="secondary">Pass</Badge>;
}

function riskBadge(risk: string | null) {
  if (risk === "high") {
    return <Badge variant="destructive">High</Badge>;
  }
  if (risk === "medium") {
    return <Badge variant="warning">Medium</Badge>;
  }
  if (risk === "low") {
    return <Badge variant="secondary">Low</Badge>;
  }
  return <Badge variant="outline">-</Badge>;
}

export default function PortCheckerPage() {
  const [target, setTarget] = useState("");
  const [mode, setMode] = useState<"quick" | "extended">("quick");
  const [customPorts, setCustomPorts] = useState("");
  const [timeoutMs, setTimeoutMs] = useState("1500");
  const [report, setReport] = useState<PortCheckerReport | null>(null);

  const mutation = useMutation({
    mutationFn: runPortChecker,
    onMutate: () => {
      setReport(null);
    },
    onSuccess: (data) => {
      setReport(data);
      toast.success("Port check completed");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const topRiskyOpenPorts = (report?.ports ?? [])
    .filter((row) => row.state === "open" && row.risk === "high")
    .slice(0, 8);

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1 px-2" asChild>
          <Link href="/vapt-web-health">
            <ArrowLeft className="h-4 w-4" />
            VAPT &amp; Web Health
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">Port Checker</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Scan TCP port exposure using quick profile or extended custom port sets.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShieldCheck className="h-5 w-5" />
            Run port scan
          </CardTitle>
          <CardDescription>
            Use this only for systems you own or are explicitly authorized to assess.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="grid gap-3 md:grid-cols-2">
            <Input
              placeholder="example.com or 1.2.3.4"
              value={target}
              onChange={(e) => setTarget(e.target.value)}
            />
            <select
              className="h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={mode}
              onChange={(e) => setMode(e.target.value as "quick" | "extended")}
            >
              <option value="quick">Quick profile</option>
              <option value="extended">Extended custom ports</option>
            </select>
          </div>

          {mode === "extended" ? (
            <Input
              placeholder="80,443,8080,1-1024"
              value={customPorts}
              onChange={(e) => setCustomPorts(e.target.value)}
            />
          ) : null}

          <div className="grid gap-3 md:grid-cols-[220px_auto]">
            <Input
              type="number"
              min={300}
              max={5000}
              value={timeoutMs}
              onChange={(e) => setTimeoutMs(e.target.value)}
            />
            <Button
              onClick={() =>
                mutation.mutate({
                  target,
                  mode,
                  custom_ports: mode === "extended" ? customPorts : undefined,
                  timeout_ms: Number(timeoutMs) || 1500,
                })
              }
              disabled={mutation.isPending || target.trim() === ""}
            >
              {mutation.isPending ? "Scanning..." : "Run Port Checker"}
            </Button>
          </div>
        </CardContent>
      </Card>

      {report ? (
        <>
          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Scan Context</CardTitle>
              <CardDescription>Target identity and detected edge/firewall provider.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p>Target: {report.target}</p>
              <p>Resolved Host: {report.host || "-"}</p>
              <p>Mode: {report.mode}</p>
              <p>Scanned At: {new Date(report.scanned_at).toLocaleString()}</p>
              <p>Detected Edge Provider: {report.edge_provider?.name || "Unknown"}</p>
              <p>Confidence: {report.edge_provider?.confidence || "low"}</p>
              <p>Note: {report.edge_provider?.note || "No edge provider fingerprint found."}</p>
            </CardContent>
          </Card>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card className="border-border/60"><CardHeader className="pb-2"><CardDescription>Total</CardDescription><CardTitle className="text-2xl">{report.summary.total_ports}</CardTitle></CardHeader></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardDescription>Open</CardDescription><CardTitle className="text-2xl">{report.summary.open_ports}</CardTitle></CardHeader></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardDescription>Closed</CardDescription><CardTitle className="text-2xl">{report.summary.closed_ports}</CardTitle></CardHeader></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardDescription>Filtered</CardDescription><CardTitle className="text-2xl">{report.summary.filtered_ports}</CardTitle></CardHeader></Card>
            <Card className="border-border/60"><CardHeader className="pb-2"><CardDescription>Risky Open</CardDescription><CardTitle className="text-2xl">{report.summary.risky_open_ports}</CardTitle></CardHeader></Card>
          </div>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Top Risky Open Ports</CardTitle>
              <CardDescription>High-risk reachable ports detected in this scan.</CardDescription>
            </CardHeader>
            <CardContent className="text-sm">
              {topRiskyOpenPorts.length === 0 ? (
                <p className="text-muted-foreground">No high-risk open ports detected in this result set.</p>
              ) : (
                <div className="space-y-1">
                  {topRiskyOpenPorts.map((row) => (
                    <p key={`risky-${row.port}`}>
                      Port {row.port} ({row.service_hint})
                      {row.latency_ms != null ? ` - ${row.latency_ms} ms` : ""}
                    </p>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="border-border/60">
            <CardHeader>
              <CardTitle>Findings</CardTitle>
              <CardDescription>Status, explanation, and recommended actions.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {report.findings.map((item) => (
                <div key={item.key} className="rounded-md border border-border/60 p-3">
                  <div className="mb-1 flex flex-wrap items-center gap-2">
                    <p className="font-medium">{item.title}</p>
                    {findingBadge(item)}
                    <Badge variant="outline">{item.severity}</Badge>
                  </div>
                  <p className="text-sm text-muted-foreground">{item.explanation}</p>
                  <p className="mt-1 text-sm"><span className="font-medium">Suggestion:</span> {item.suggestion}</p>
                  {item.evidence.length > 0 ? (
                    <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                      {item.evidence.map((row, idx) => (
                        <p key={`${item.key}-${idx}`}>- {row}</p>
                      ))}
                    </div>
                  ) : null}
                </div>
              ))}
            </CardContent>
          </Card>

          <Card className="border-border/60">
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <CardTitle>Port Results</CardTitle>
                <CardDescription>Detailed result for each scanned TCP port.</CardDescription>
              </div>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() =>
                    downloadTextFile(
                      "port-checker-report.json",
                      JSON.stringify(report, null, 2),
                      "application/json;charset=utf-8",
                    )
                  }
                >
                  Export JSON
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => downloadTextFile("port-checker-ports.csv", toPortsCsv(report), "text/csv;charset=utf-8")}
                >
                  Export CSV
                </Button>
              </div>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border/60 text-left">
                    <th className="py-2 pr-3">Port</th>
                    <th className="py-2 pr-3">State</th>
                    <th className="py-2 pr-3">Latency</th>
                    <th className="py-2 pr-3">Service</th>
                    <th className="py-2 pr-3">Risk</th>
                    <th className="py-2 pr-3">Banner / Error</th>
                  </tr>
                </thead>
                <tbody>
                  {report.ports.map((row) => (
                    <tr key={`port-${row.port}`} className="border-b border-border/40 align-top">
                      <td className="py-2 pr-3 font-medium">{row.port}</td>
                      <td className="py-2 pr-3">{row.state}</td>
                      <td className="py-2 pr-3">{row.latency_ms != null ? `${row.latency_ms} ms` : "-"}</td>
                      <td className="py-2 pr-3">{row.service_hint}</td>
                      <td className="py-2 pr-3">{riskBadge(row.risk)}</td>
                      <td className="py-2 pr-3 text-muted-foreground">{row.banner || row.error || "-"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </>
      ) : null}
    </div>
  );
}
