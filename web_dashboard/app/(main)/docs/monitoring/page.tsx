import Link from "next/link";

export default function DocsMonitoringPage() {
  return (
    <article className="space-y-8 text-sm leading-relaxed">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Site monitoring</h1>
        <p className="mt-2 text-muted-foreground">
          Scheduled and on-demand checks run through Laravel queues. How to <strong className="text-foreground">define</strong> checks (types, JSON) and{" "}
          <strong className="text-foreground">configure</strong> behaviour via env is in{" "}
          <Link href="/docs/configure" className="font-medium text-primary underline-offset-4 hover:underline">
            Configure &amp; use
          </Link>{" "}
          (§5 and monitoring env table).
        </p>
      </div>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Using the Monitoring screen</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>
            Select the correct <strong className="text-foreground">organization</strong> in the header — checks are scoped to that org.
          </li>
          <li>
            Open <strong className="text-foreground">Monitoring</strong> in the sidebar to list checks and last status.
          </li>
          <li>
            Use <strong className="text-foreground">Run now</strong> (or equivalent action) to queue a single execution — requires{" "}
            <code className="rounded bg-muted px-1 text-xs">php artisan horizon</code> (or another worker) processing the{" "}
            <code className="rounded bg-muted px-1 text-xs">site-monitoring</code> queue.
          </li>
          <li>
            Scheduled runs fire only if <code className="rounded bg-muted px-1 text-xs">php artisan schedule:work</code> (or cron) is running: every minute Laravel
            dispatches due checks to the queue.
          </li>
        </ol>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Check types (implemented)</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <strong className="text-foreground">uptime, http, https</strong> — HTTP probe; optional GET with response body preview in logs; retries on timeouts and
            5xx/429.
          </li>
          <li>
            <strong className="text-foreground">ssl, tls</strong> — Certificate expiry from TLS handshake; retries on connect failures.
          </li>
          <li>
            <strong className="text-foreground">domain, whois</strong> — Domain expiry via RDAP when{" "}
            <code className="rounded bg-muted px-1 text-xs">SITE_MONITORING_DOMAIN_PROVIDER=rdap</code>.
          </li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Scheduling & queues</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>
            <strong className="text-foreground">Scheduler</strong> (<code className="rounded bg-muted px-1 text-xs">routes/console.php</code>) dispatches{" "}
            <code className="rounded bg-muted px-1 text-xs">DispatchDueMonitoringChecksJob</code> every minute.
          </li>
          <li>
            That job enqueues <code className="rounded bg-muted px-1 text-xs">RunMonitoringCheckJob</code> per due check on the queue named by{" "}
            <code className="rounded bg-muted px-1 text-xs">SITE_MONITORING_QUEUE</code> (default <code className="rounded bg-muted px-1 text-xs">site-monitoring</code>).
          </li>
          <li>
            <strong className="text-foreground">Horizon</strong> must process both <code className="rounded bg-muted px-1 text-xs">default</code> and{" "}
            <code className="rounded bg-muted px-1 text-xs">site-monitoring</code> — see <code className="rounded bg-muted px-1 text-xs">config/horizon.php</code>.
          </li>
        </ol>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Execution, logs, notifications</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            Per-check <strong className="text-foreground">cache lock</strong> plus queue <code className="rounded bg-muted px-1 text-xs">WithoutOverlapping</code> avoid
            concurrent duplicate runs for the same check id.
          </li>
          <li>
            Each run writes a <code className="rounded bg-muted px-1 text-xs">monitoring_logs</code> row (status, timings, JSON <code className="rounded bg-muted px-1 text-xs">meta</code> including HTTP headers/body preview when applicable) and updates the check row (last status, <code className="rounded bg-muted px-1 text-xs">next_run_at</code>, consecutive failures).
          </li>
          <li>
            <strong className="text-foreground">Notifications</strong> — uptime failure after N consecutive failures, recovery, SSL/domain “expiring soon” with throttle
            keys to reduce noise.
          </li>
        </ul>
      </section>
    </article>
  );
}
