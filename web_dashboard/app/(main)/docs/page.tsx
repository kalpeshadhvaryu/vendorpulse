import Link from "next/link";
import { DOC_PAGES } from "@/lib/docs-nav";
import { Button } from "@/components/ui/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function DocsOverviewPage() {
  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Documentation</h1>
        <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
          Guides for installing, configuring, and operating VendorPulse: the Next.js dashboard and the Laravel API.
        </p>
        <div className="mt-4">
          <Button asChild size="sm" className="font-medium">
            <Link href="/docs/configure">How to configure &amp; use — start here</Link>
          </Button>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        {DOC_PAGES.filter((p) => p.href !== "/docs").map(({ href, label, icon: Icon }) => (
          <Link key={href} href={href}>
            <Card className="h-full border-border/60 transition-colors hover:border-primary/30 hover:bg-muted/30">
              <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <Icon className="h-4 w-4" />
                </div>
                <div className="min-w-0">
                  <CardTitle className="text-base">{label}</CardTitle>
                  <CardDescription className="mt-1 text-xs">Open guide →</CardDescription>
                </div>
              </CardHeader>
            </Card>
          </Link>
        ))}
      </div>

      <section className="space-y-3 text-sm leading-relaxed text-muted-foreground">
        <h2 className="text-sm font-semibold text-foreground">What each guide covers</h2>
        <ul className="list-inside list-disc space-y-1">
          <li>
            <strong className="text-foreground">Getting started</strong> — shortest path to run API + dashboard.
          </li>
          <li>
            <strong className="text-foreground">Help guide</strong> — setup notes and quick troubleshooting, including WHM/cPanel server monitor tips.
          </li>
          <li>
            <strong className="text-foreground">Configure &amp; use</strong> — env tables, checklist, how to use each screen, monitoring JSON + curl examples.
          </li>
          <li>
            <strong className="text-foreground">Vendors &amp; invoices</strong> — setup flow, data model, CRUD workflow, renewals and due-date practices.
          </li>
          <li>
            <strong className="text-foreground">API reference</strong> — auth headers, routes, curl snippets.
          </li>
          <li>
            <strong className="text-foreground">Site monitoring</strong> — how checks run on queues, logs, notifications, retries.
          </li>
          <li>
            <strong className="text-foreground">Operations</strong> — Horizon, scheduler, cron, troubleshooting.
          </li>
        </ul>
      </section>
    </div>
  );
}
