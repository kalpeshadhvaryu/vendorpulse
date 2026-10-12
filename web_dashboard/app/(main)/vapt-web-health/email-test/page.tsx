"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { ArrowLeft, Copy, Loader2, MailCheck } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { GRADE_STYLES, ScoreBreakdown } from "@/components/vapt/email-security-card";
import { getApiErrorMessage } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import type { DnsCheckItem, EmailDeliverabilityTest, EmailTestResult } from "@/lib/api/types";
import { createEmailTest, fetchEmailTest, fetchEmailTests } from "@/lib/api/vapt-web-health";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

const STATUS_RANK: Record<DnsCheckItem["status"], number> = { fail: 0, warning: 1, pass: 2 };

function statusBadge(status: DnsCheckItem["status"]) {
  if (status === "fail") return <Badge variant="destructive">Fail</Badge>;
  if (status === "warning") return <Badge variant="warning">Warning</Badge>;
  return <Badge variant="success">Pass</Badge>;
}

function authBadge(result: string | null | undefined) {
  if (!result) return <Badge variant="outline">missing</Badge>;
  return <Badge variant={result === "pass" ? "success" : result === "none" ? "warning" : "destructive"}>{result}</Badge>;
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="rounded-md border border-border/60 p-3">
      <p className="text-xs text-muted-foreground">{label}</p>
      <div className="mt-0.5 break-all text-sm">{children}</div>
    </div>
  );
}

function ResultView({ test, result }: { test: EmailDeliverabilityTest; result: EmailTestResult }) {
  const { message, authentication: auth, alignment, sending_ip: ip, tls } = result;
  const checks = [...result.checks].sort((a, b) => STATUS_RANK[a.status] - STATUS_RANK[b.status]);

  return (
    <>
      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Result</CardTitle>
          <CardDescription>
            Received {test.received_at ? new Date(test.received_at).toLocaleString() : ""} from {message.from ?? "unknown sender"}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-4">
            <div
              className={cn(
                "flex h-16 w-16 items-center justify-center rounded-xl text-3xl font-bold",
                GRADE_STYLES[result.grade],
              )}
            >
              {result.grade}
            </div>
            <p className="text-2xl font-semibold">{result.score}/100</p>
            <ScoreBreakdown rows={result.breakdown} />
          </div>

          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Fact label="Subject">{message.subject ?? "—"}</Fact>
            <Fact label="Envelope sender (Return-Path)">
              <span className="font-mono text-xs">{message.return_path ?? auth?.spf?.mailfrom ?? "—"}</span>
            </Fact>
            <Fact label="Sending server">
              {ip ? (
                <>
                  <span className="font-mono text-xs">{ip.ip}</span>
                  <span className="block font-mono text-xs text-muted-foreground">{ip.ptr.join(", ") || "no reverse DNS"}</span>
                </>
              ) : (
                "Unknown"
              )}
            </Fact>
            <Fact label="Encryption in transit">
              {tls.used === true ? "TLS" : tls.used === false ? "Not encrypted" : "Unknown"}
              {tls.detail ? <span className="text-muted-foreground"> · {tls.detail}</span> : null}
            </Fact>
            <Fact label="DKIM selector">
              {result.dkim_signatures.length ? (
                result.dkim_signatures.map((sig, idx) => (
                  <span key={idx} className="block font-mono text-xs">
                    {sig.selector ?? "?"}
                    {sig.domain ? <span className="text-muted-foreground"> ({sig.domain})</span> : null}
                  </span>
                ))
              ) : (
                "Not signed"
              )}
            </Fact>
            <Fact label="Hops">
              {message.received_hops}
              {message.x_mailer ? <span className="text-muted-foreground"> · {message.x_mailer}</span> : null}
            </Fact>
            <Fact label="List-Unsubscribe">
              {message.list_unsubscribe ? (message.list_unsubscribe_one_click ? "Yes (one-click)" : "Yes") : "No"}
            </Fact>
          </div>

          <div className="overflow-x-auto rounded-md border border-border/60">
            <table className="w-full text-sm">
              <thead className="bg-muted/40 text-left text-xs text-muted-foreground">
                <tr>
                  <th className="px-3 py-2 font-medium">Check</th>
                  <th className="px-3 py-2 font-medium">Result</th>
                  <th className="px-3 py-2 font-medium">Domain</th>
                  <th className="px-3 py-2 font-medium">Aligned with From ({alignment.from_domain ?? "?"})</th>
                </tr>
              </thead>
              <tbody>
                <tr className="border-t border-border/60">
                  <td className="px-3 py-2">SPF</td>
                  <td className="px-3 py-2">{authBadge(auth?.spf?.result)}</td>
                  <td className="px-3 py-2 font-mono text-xs">{alignment.mailfrom_domain ?? "—"}</td>
                  <td className="px-3 py-2">{alignment.spf_aligned ? "Yes" : "No"}</td>
                </tr>
                <tr className="border-t border-border/60">
                  <td className="px-3 py-2">DKIM</td>
                  <td className="px-3 py-2">{authBadge(auth?.dkim[0]?.result)}</td>
                  <td className="px-3 py-2 font-mono text-xs">{alignment.dkim_domains.join(", ") || "—"}</td>
                  <td className="px-3 py-2">{alignment.dkim_aligned ? "Yes" : "No"}</td>
                </tr>
                <tr className="border-t border-border/60">
                  <td className="px-3 py-2">DMARC</td>
                  <td className="px-3 py-2">{authBadge(auth?.dmarc?.result)}</td>
                  <td className="px-3 py-2 font-mono text-xs">{auth?.dmarc?.header_from ?? alignment.from_domain ?? "—"}</td>
                  <td className="px-3 py-2 text-muted-foreground">{auth?.dmarc?.policy ? `policy ${auth.dmarc.policy}` : ""}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Findings</CardTitle>
          <CardDescription>Every check includes status, explanation, and recommendation.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {checks.map((item) => (
            <div key={item.key} className="rounded-md border border-border/60 p-3">
              <div className="mb-1 flex flex-wrap items-center gap-2">
                <p className="font-medium">{item.title}</p>
                {statusBadge(item.status)}
                <Badge variant="outline">{item.severity}</Badge>
              </div>
              <p className="text-sm text-muted-foreground">{item.explanation}</p>
              {item.suggestion ? (
                <p className="mt-1 text-sm">
                  <span className="font-medium">Suggestion:</span> {item.suggestion}
                </p>
              ) : null}
              {item.evidence.length > 0 ? (
                <div className="mt-2 space-y-1 font-mono text-xs text-muted-foreground">
                  {item.evidence.map((row, idx) => (
                    <p key={`${item.key}-${idx}`} className="break-all">
                      {row}
                    </p>
                  ))}
                </div>
              ) : null}
            </div>
          ))}
          {auth ? (
            <div className="space-y-1">
              <p className="text-xs text-muted-foreground">Authentication-Results from {auth.authserv_id || "the receiving server"}</p>
              <pre className="max-h-48 overflow-auto whitespace-pre-wrap break-all rounded bg-muted p-3 text-xs">{auth.raw}</pre>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </>
  );
}

export default function EmailTestPage() {
  const queryClient = useQueryClient();
  const organizationId = useAuthStore((s) => s.organizationId);
  const [activeId, setActiveId] = useState<string | null>(null);

  const listQuery = useQuery({
    queryKey: queryKeys.emailTests(organizationId),
    queryFn: fetchEmailTests,
  });

  const activeQuery = useQuery({
    queryKey: queryKeys.emailTest(activeId ?? ""),
    queryFn: () => fetchEmailTest(activeId as string),
    enabled: activeId !== null,
    refetchInterval: (query) => (query.state.data?.status === "waiting" ? 5000 : false),
  });

  const createMutation = useMutation({
    mutationFn: createEmailTest,
    onSuccess: (test) => {
      queryClient.setQueryData(queryKeys.emailTest(test.id), test);
      setActiveId(test.id);
      void queryClient.invalidateQueries({ queryKey: ["email-tests"] });
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const active = activeQuery.data ?? null;
  const activeStatus = active?.status;
  const previousStatus = useRef<string | undefined>(undefined);

  useEffect(() => {
    if (previousStatus.current === "waiting" && activeStatus && activeStatus !== "waiting") {
      void queryClient.invalidateQueries({ queryKey: ["email-tests"] });
      if (activeStatus === "received") {
        toast.success("Test email received");
      }
    }
    previousStatus.current = activeStatus;
  }, [activeStatus, queryClient]);

  useEffect(() => {
    setActiveId(null);
  }, [organizationId]);

  const available = listQuery.data?.available ?? true;
  const history = listQuery.data?.items ?? [];
  const baseAddress = active?.address.replace(/\+vp-[a-z0-9]+@/i, "@");

  const copyAddress = async () => {
    if (!active) return;
    try {
      await navigator.clipboard.writeText(active.address);
      toast.success("Address copied");
    } catch {
      toast.error("Could not copy. Select the address and copy it manually.");
    }
  };

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1 px-2" asChild>
          <Link href="/vapt-web-health">
            <ArrowLeft className="h-4 w-4" />
            VAPT &amp; Web Health
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">Email Deliverability Test</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Send a real email and see how receiving servers judge it: SPF, DKIM, DMARC, alignment, sender reputation and
          encryption.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MailCheck className="h-5 w-5" />
            Start a test
          </CardTitle>
          <CardDescription>
            You get a unique address valid for 30 minutes. Send to it from the system you want to test: Outlook, Gmail,
            your website&apos;s contact form, CRM or newsletter tool.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {!available ? (
            <p className="text-sm text-destructive">
              The test mailbox is not configured on this server. Ask an administrator to set the EMAIL_TEST_* settings.
            </p>
          ) : null}
          <Button onClick={() => createMutation.mutate()} disabled={!available || createMutation.isPending}>
            {createMutation.isPending ? "Creating..." : active ? "Start a new test" : "Get test address"}
          </Button>

          {active && active.status === "waiting" ? (
            <div className="space-y-2 rounded-md border border-dashed border-primary/60 bg-primary/5 p-4">
              <div className="flex flex-wrap items-center gap-2">
                <code className="flex-1 break-all text-base font-medium">{active.address}</code>
                <Button variant="outline" size="sm" className="gap-1" onClick={copyAddress}>
                  <Copy className="h-4 w-4" />
                  Copy
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                Any subject works. If your system rejects the &quot;+&quot; in the address, send to{" "}
                <span className="font-medium text-foreground">{baseAddress}</span> with the subject{" "}
                <span className="font-medium text-foreground">{active.subject_hint}</span> instead.
              </p>
              <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Waiting for your email… expires {new Date(active.expires_at).toLocaleTimeString()}
              </p>
            </div>
          ) : null}

          {active && active.status === "expired" ? (
            <p className="text-sm text-muted-foreground">
              The address {active.address} expired before an email arrived. Start a new test.
            </p>
          ) : null}
        </CardContent>
      </Card>

      {active && active.status === "received" && active.result ? <ResultView test={active} result={active.result} /> : null}

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Recent tests</CardTitle>
          <CardDescription>The last 20 tests for this organization. Results are kept for 7 days.</CardDescription>
        </CardHeader>
        <CardContent>
          {history.length === 0 ? (
            <p className="text-sm text-muted-foreground">No tests yet.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="py-2 pr-3 font-medium">Created</th>
                    <th className="py-2 pr-3 font-medium">Status</th>
                    <th className="py-2 pr-3 font-medium">From</th>
                    <th className="py-2 pr-3 font-medium">Subject</th>
                    <th className="py-2 pr-3 font-medium">Grade</th>
                    <th className="py-2" />
                  </tr>
                </thead>
                <tbody>
                  {history.map((item) => (
                    <tr key={item.id} className={cn("border-t border-border/60", item.id === activeId && "bg-muted/40")}>
                      <td className="py-2 pr-3 whitespace-nowrap">{new Date(item.created_at).toLocaleString()}</td>
                      <td className="py-2 pr-3">
                        <Badge
                          variant={
                            item.status === "received" ? "success" : item.status === "waiting" ? "secondary" : "outline"
                          }
                        >
                          {item.status}
                        </Badge>
                      </td>
                      <td className="py-2 pr-3 font-mono text-xs">{item.from_address ?? "—"}</td>
                      <td className="max-w-[240px] truncate py-2 pr-3">{item.subject ?? "—"}</td>
                      <td className="py-2 pr-3">
                        {item.grade ? (
                          <span className={cn("rounded px-2 py-0.5 font-semibold", GRADE_STYLES[item.grade])}>
                            {item.grade} · {item.score}
                          </span>
                        ) : (
                          "—"
                        )}
                      </td>
                      <td className="py-2 text-right">
                        {item.status !== "expired" ? (
                          <Button variant="ghost" size="sm" onClick={() => setActiveId(item.id)}>
                            View
                          </Button>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
