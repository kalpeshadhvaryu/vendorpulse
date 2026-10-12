"use client";

import { useState, type ReactNode } from "react";
import Link from "next/link";
import { Mail } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import type { EmailSecurityReport } from "@/lib/api/types";
import { cn } from "@/lib/utils";

export const GRADE_STYLES: Record<EmailSecurityReport["grade"], string> = {
  A: "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300",
  B: "bg-lime-500/15 text-lime-700 dark:text-lime-300",
  C: "bg-amber-500/15 text-amber-800 dark:text-amber-200",
  D: "bg-orange-500/15 text-orange-700 dark:text-orange-300",
  F: "bg-destructive/15 text-destructive",
};

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="space-y-2 rounded-md border border-border/60 p-3">
      <p className="text-sm font-medium">{title}</p>
      {children}
    </div>
  );
}

function Mono({ children }: { children: ReactNode }) {
  return <p className="break-all rounded bg-muted px-2 py-1 font-mono text-xs">{children}</p>;
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <p className="text-xs text-muted-foreground">
      <span className="font-medium text-foreground">{label}:</span> {children}
    </p>
  );
}

export function ScoreBreakdown({ rows }: { rows: EmailSecurityReport["breakdown"] }) {
  return (
    <div className="grid min-w-[240px] flex-1 gap-1.5">
      {rows.map((row) => (
        <div key={row.key} className="flex items-center gap-2 text-xs">
          <span className="w-24 shrink-0 text-muted-foreground">{row.label}</span>
          <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
            <div
              className={cn(
                "h-full rounded-full",
                row.earned === row.possible ? "bg-emerald-500" : row.earned === 0 ? "bg-destructive" : "bg-amber-500",
              )}
              style={{ width: `${row.possible ? (row.earned / row.possible) * 100 : 0}%` }}
            />
          </div>
          <span className="w-12 shrink-0 text-right tabular-nums">
            {row.earned}/{row.possible}
          </span>
        </div>
      ))}
    </div>
  );
}

function DkimFinder({
  domain,
  found,
  pending,
  onCheck,
}: {
  domain: string;
  found: boolean;
  pending: boolean;
  onCheck: (selector: string) => void;
}) {
  const [value, setValue] = useState("");

  const body = (
    <div className="space-y-3">
      <ol className="list-decimal space-y-1.5 pl-5 text-xs text-muted-foreground">
        <li>
          <span className="font-medium text-foreground">Easiest:</span>{" "}
          <Link href="/vapt-web-health/email-test" className="text-primary underline-offset-2 hover:underline">
            send a test email
          </Link>{" "}
          from {domain}. The result shows your selector automatically.
        </li>
        <li>
          Or open an email you sent and view its source (Gmail: ⋮ → Show original, Outlook: View → View message source).
          Find the line starting with <code className="rounded bg-muted px-1">DKIM-Signature</code>; the selector is the
          value after <code className="rounded bg-muted px-1">s=</code>. You can paste the whole line.
        </li>
        <li>
          Or check your mail provider&apos;s DKIM settings: the selector is the first part of the DNS name they asked you to
          add (for <code className="rounded bg-muted px-1">mail2024._domainkey.{domain}</code> it is{" "}
          <code className="rounded bg-muted px-1">mail2024</code>).
        </li>
      </ol>
      <form
        className="flex flex-col gap-2 sm:flex-row"
        onSubmit={(e) => {
          e.preventDefault();
          if (value.trim() !== "" && !pending) {
            onCheck(value.trim());
          }
        }}
      >
        <Input
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder="Selector, DNS name, or pasted DKIM-Signature line"
        />
        <Button type="submit" disabled={pending || value.trim() === ""}>
          {pending ? "Checking..." : "Check again"}
        </Button>
      </form>
    </div>
  );

  if (found) {
    return (
      <details className="rounded-md border border-border/60 p-3 text-sm">
        <summary className="cursor-pointer font-medium text-primary">Your provider uses another selector? Check it</summary>
        <div className="mt-3">{body}</div>
      </details>
    );
  }

  return (
    <div className="space-y-2 rounded-md border border-primary/30 bg-primary/5 p-3 text-sm">
      <p>
        <span className="font-medium">Find my DKIM key.</span>{" "}
        <span className="text-muted-foreground">
          DNS can&apos;t list DKIM keys, so we try common names (&quot;selectors&quot;). Your provider may use its own
          name. Three ways to find it:
        </span>
      </p>
      {body}
    </div>
  );
}

export function EmailSecurityCard({
  report,
  onCheckSelector,
  checkingSelector = false,
}: {
  report: EmailSecurityReport;
  onCheckSelector?: (selector: string) => void;
  checkingSelector?: boolean;
}) {
  const { spf, dmarc, dkim, mta_sts: mtaSts, tls_rpt: tlsRpt, bimi, blacklist } = report;
  const listed = blacklist.results.filter((r) => r.status === "listed");

  return (
    <Card className="border-border/60">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Mail className="h-5 w-5" />
          Email security &amp; deliverability
        </CardTitle>
        <CardDescription>
          SPF, DKIM, DMARC, encrypted delivery, reverse DNS and blacklist status for {report.domain}.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-4">
          <div
            className={cn(
              "flex h-16 w-16 items-center justify-center rounded-xl text-3xl font-bold",
              GRADE_STYLES[report.grade],
            )}
          >
            {report.grade}
          </div>
          <div>
            <p className="text-2xl font-semibold">{report.score}/100</p>
            <p className="text-xs text-muted-foreground">
              {report.null_mx
                ? "Domain declares it does not receive email (null MX)."
                : report.receives_mail
                  ? "Domain receives email."
                  : "No MX records: domain does not receive email."}
            </p>
          </div>
          <ScoreBreakdown rows={report.breakdown} />
        </div>

        <div className="grid gap-3 md:grid-cols-2">
          <Section title="SPF">
            {spf.record ? <Mono>{spf.record}</Mono> : <p className="text-xs text-muted-foreground">No SPF record.</p>}
            {spf.records.length > 1 ? <Row label="Records found">{spf.records.length} (only one is allowed)</Row> : null}
            {spf.record ? (
              <>
                <Row label="Default policy">{spf.all ?? "missing"}</Row>
                <Row label="DNS lookups">
                  {spf.lookups} / 10{spf.loop ? " (include loop detected)" : ""}
                </Row>
                {spf.includes.length > 0 ? <Row label="Includes">{spf.includes.join(", ")}</Row> : null}
                {spf.errors.map((error) => (
                  <p key={error} className="text-xs text-destructive">
                    {error}
                  </p>
                ))}
              </>
            ) : null}
          </Section>

          <Section title="DMARC">
            {dmarc.record ? <Mono>{dmarc.record}</Mono> : <p className="text-xs text-muted-foreground">No DMARC record.</p>}
            {dmarc.record ? (
              <>
                <Row label="Policy">
                  {dmarc.tags.p ?? "missing"} · subdomains {dmarc.tags.sp ?? "same"} · pct {dmarc.tags.pct ?? 100}
                </Row>
                <Row label="Alignment">
                  DKIM {dmarc.tags.adkim === "s" ? "strict" : "relaxed"}, SPF {dmarc.tags.aspf === "s" ? "strict" : "relaxed"}
                </Row>
                <Row label="Reports (rua)">{dmarc.tags.rua?.length ? dmarc.tags.rua.join(", ") : "none"}</Row>
                {dmarc.source !== report.domain ? <Row label="Inherited from">{dmarc.source}</Row> : null}
              </>
            ) : null}
          </Section>
        </div>

        <Section title={`DKIM (${dkim.selectors_checked} selectors checked)`}>
          {dkim.custom_selector ? (
            <Row label={`Selector "${dkim.custom_selector}"`}>{dkim.custom_selector_found ? "found" : "not found"}</Row>
          ) : null}
          {dkim.keys.length === 0 ? (
            <p className="text-xs text-muted-foreground">
              No DKIM key found on the {dkim.selectors_checked} common selector names we tried.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead className="text-left text-muted-foreground">
                  <tr>
                    <th className="py-1 pr-3 font-medium">Selector</th>
                    <th className="py-1 pr-3 font-medium">Type</th>
                    <th className="py-1 pr-3 font-medium">Key size</th>
                    <th className="py-1 font-medium">Notes</th>
                  </tr>
                </thead>
                <tbody>
                  {dkim.keys.map((key) => (
                    <tr key={key.selector} className="border-t border-border/60 align-top">
                      <td className="py-1.5 pr-3 font-mono">{key.selector}</td>
                      <td className="py-1.5 pr-3 uppercase">{key.key_type}</td>
                      <td className="py-1.5 pr-3">{key.key_bits ? `${key.key_bits}-bit` : "—"}</td>
                      <td className="py-1.5">
                        {key.issues.length ? key.issues.join(" ") : <span className="text-emerald-600">OK</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {onCheckSelector ? (
            <DkimFinder
              key={`${report.domain}-${dkim.custom_selector ?? ""}`}
              domain={report.domain}
              found={dkim.keys.some((key) => !key.revoked)}
              pending={checkingSelector}
              onCheck={onCheckSelector}
            />
          ) : null}
        </Section>

        {report.mx_hosts.length > 0 ? (
          <Section title="Mail servers & reverse DNS">
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead className="text-left text-muted-foreground">
                  <tr>
                    <th className="py-1 pr-3 font-medium">MX host</th>
                    <th className="py-1 pr-3 font-medium">IP</th>
                    <th className="py-1 pr-3 font-medium">PTR</th>
                    <th className="py-1 font-medium">Forward-confirmed</th>
                  </tr>
                </thead>
                <tbody>
                  {report.mx_hosts.flatMap((mx) =>
                    (mx.ips.length ? mx.ips : [null]).map((ip, idx) => (
                      <tr key={`${mx.host}-${ip?.ip ?? idx}`} className="border-t border-border/60">
                        <td className="py-1.5 pr-3 font-mono">
                          {idx === 0 ? `${mx.host} (${mx.priority})` : ""}
                        </td>
                        <td className="py-1.5 pr-3 font-mono">{ip?.ip ?? "no IPv4"}</td>
                        <td className="py-1.5 pr-3 font-mono">{ip?.ptr.join(", ") || "—"}</td>
                        <td className="py-1.5">
                          {ip ? (
                            <Badge variant={ip.fcrdns ? "success" : "warning"}>{ip.fcrdns ? "Yes" : "No"}</Badge>
                          ) : null}
                        </td>
                      </tr>
                    )),
                  )}
                </tbody>
              </table>
            </div>
          </Section>
        ) : null}

        {blacklist.enabled ? (
          <Section title="Blacklists">
            <Row label="Lookups">
              {blacklist.checked} · listed {blacklist.listed} · unverified {blacklist.errors}
            </Row>
            {listed.length > 0 ? (
              listed.map((r) => (
                <p key={`${r.target}-${r.list}`} className="text-xs text-destructive">
                  {r.target} is listed on {r.list} ({r.response})
                </p>
              ))
            ) : (
              <p className="text-xs text-emerald-600">Not listed on any checked blacklist.</p>
            )}
          </Section>
        ) : null}

        <div className="grid gap-3 md:grid-cols-3">
          <Section title="MTA-STS">
            {mtaSts.record ? <Mono>{mtaSts.record}</Mono> : <p className="text-xs text-muted-foreground">Not configured.</p>}
            {mtaSts.policy ? <Row label="Mode">{mtaSts.policy.mode}</Row> : null}
            {mtaSts.error ? <p className="text-xs text-destructive">{mtaSts.error}</p> : null}
            {mtaSts.unmatched_mx.length ? (
              <p className="text-xs text-destructive">Not in policy: {mtaSts.unmatched_mx.join(", ")}</p>
            ) : null}
          </Section>
          <Section title="TLS-RPT">
            {tlsRpt.record ? <Mono>{tlsRpt.record}</Mono> : <p className="text-xs text-muted-foreground">Not configured.</p>}
          </Section>
          <Section title="BIMI">
            {bimi.record ? <Mono>{bimi.record}</Mono> : <p className="text-xs text-muted-foreground">Not configured (optional).</p>}
          </Section>
        </div>
      </CardContent>
    </Card>
  );
}
