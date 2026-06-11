import Link from "next/link";
import { ConfigTable } from "@/components/docs/config-table";

export default function DocsConfigurePage() {
  return (
    <article className="space-y-10 text-sm leading-relaxed">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">How to configure &amp; use</h1>
        <p className="mt-2 text-muted-foreground">
          Step-by-step: environment variables, where files live, how to run processes, and how to use each area of the
          dashboard. For deeper API or monitoring internals, see{" "}
          <Link href="/docs/api" className="font-medium text-primary underline-offset-4 hover:underline">
            API reference
          </Link>{" "}
          and{" "}
          <Link href="/docs/monitoring" className="font-medium text-primary underline-offset-4 hover:underline">
            Site monitoring
          </Link>
          .
        </p>
      </div>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">1. End-to-end checklist</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>
            <strong className="text-foreground">Laravel</strong>: <code className="rounded bg-muted px-1 text-xs">.env</code> with{" "}
            <code className="rounded bg-muted px-1 text-xs">APP_KEY</code>, database, <code className="rounded bg-muted px-1 text-xs">QUEUE_CONNECTION=redis</code>, Redis
            host, <code className="rounded bg-muted px-1 text-xs">CORS_ALLOWED_ORIGINS</code>, <code className="rounded bg-muted px-1 text-xs">SANCTUM_STATEFUL_DOMAINS</code>.
          </li>
          <li>
            <strong className="text-foreground">Migrate &amp; seed</strong>:{" "}
            <code className="rounded bg-muted px-1 text-xs">php artisan migrate</code> then{" "}
            <code className="rounded bg-muted px-1 text-xs">php artisan db:seed</code> (dev user + org).
          </li>
          <li>
            <strong className="text-foreground">API server</strong>: <code className="rounded bg-muted px-1 text-xs">php artisan serve</code> (or Apache/nginx to{" "}
            <code className="rounded bg-muted px-1 text-xs">public/</code>).
          </li>
          <li>
            <strong className="text-foreground">Dashboard</strong>: in <code className="rounded bg-muted px-1 text-xs">web_dashboard/.env.local</code> set{" "}
            <code className="rounded bg-muted px-1 text-xs">NEXT_PUBLIC_API_URL</code> to the same host/port style you use in the browser (see §2).
          </li>
          <li>
            <strong className="text-foreground">Next dev</strong>: <code className="rounded bg-muted px-1 text-xs">cd web_dashboard && npm run dev</code> — restart after
            any <code className="rounded bg-muted px-1 text-xs">.env.local</code> change.
          </li>
          <li>
            <strong className="text-foreground">Queues</strong>: Redis up → <code className="rounded bg-muted px-1 text-xs">php artisan horizon</code> → scheduler (
            <code className="rounded bg-muted px-1 text-xs">php artisan schedule:work</code> or cron).
          </li>
          <li>
            <strong className="text-foreground">Browser</strong>: open the dashboard URL, sign in, pick organization (header), use each module (§4).
          </li>
        </ol>
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">2. Configure the dashboard (Next.js)</h2>
        <p className="text-muted-foreground">
          File: <code className="rounded bg-muted px-1 text-xs">web_dashboard/.env.local</code> (create from{" "}
          <code className="rounded bg-muted px-1 text-xs">.env.local.example</code>). Only variables prefixed with{" "}
          <code className="rounded bg-muted px-1 text-xs">NEXT_PUBLIC_</code> are visible in the browser bundle.
        </p>
        <ConfigTable
          rows={[
            {
              name: "NEXT_PUBLIC_API_URL",
              description: "Laravel API base including /api/v1. No trailing slash after v1.",
              example: "http://127.0.0.1:8000/api/v1",
            },
            {
              name: "NEXT_PUBLIC_APP_URL",
              description: "Optional. Public URL of this dashboard (absolute links if you add them).",
              example: "http://127.0.0.1:3000",
            },
          ]}
        />
        <p className="text-muted-foreground">
          <strong className="text-foreground">Host matching:</strong> if your API is reachable at{" "}
          <code className="rounded bg-muted px-1 text-xs">127.0.0.1:8000</code>, use that in{" "}
          <code className="rounded bg-muted px-1 text-xs">NEXT_PUBLIC_API_URL</code> — not only{" "}
          <code className="rounded bg-muted px-1 text-xs">localhost</code> — so the browser hits the same server as{" "}
          <code className="rounded bg-muted px-1 text-xs">curl</code>.
        </p>
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">3. Configure the API (Laravel)</h2>
        <p className="text-muted-foreground">
          File: project root <code className="rounded bg-muted px-1 text-xs">.env</code>. After edits:{" "}
          <code className="rounded bg-muted px-1 text-xs">php artisan config:clear</code>.
        </p>
        <ConfigTable
          rows={[
            { name: "APP_KEY", description: "Application secret; required.", example: "base64:…" },
            { name: "DB_*", description: "Database connection for users, orgs, checks, logs.", example: "sqlite or pgsql" },
            {
              name: "QUEUE_CONNECTION",
              description: "Use redis for Horizon workers.",
              example: "redis",
            },
            {
              name: "REDIS_*",
              description: "Redis host/port/password for queues and Horizon metadata.",
              example: "127.0.0.1:6379",
            },
            {
              name: "CORS_ALLOWED_ORIGINS",
              description: "Comma-separated browser origins allowed to call the API with credentials.",
              example: "http://localhost:3000,http://127.0.0.1:3000",
            },
            {
              name: "SANCTUM_STATEFUL_DOMAINS",
              description: "Hosts (no scheme) for SPA cookie auth if you use it; align with dashboard host:port.",
              example: "localhost,localhost:3000,127.0.0.1,127.0.0.1:3000",
            },
          ]}
        />
        <p className="text-muted-foreground">
          Optional monitoring tuning (all optional; defaults in <code className="rounded bg-muted px-1 text-xs">config/site-monitoring.php</code>):
        </p>
        <ConfigTable
          rows={[
            { name: "SITE_MONITORING_QUEUE", description: "Queue name for per-check jobs.", example: "site-monitoring" },
            {
              name: "SITE_MONITORING_DOMAIN_PROVIDER",
              description: 'rdap = public RDAP lookups; null = domain checks return "skipped" until wired.',
              example: "rdap",
            },
            {
              name: "SITE_MONITORING_SSL_WARNING_DAYS",
              description: "Days before cert expiry to mark degraded + notify.",
              example: "30",
            },
            {
              name: "SITE_MONITORING_UPTIME_NOTIFY_AFTER_FAILURES",
              description: "Consecutive failures before uptime failure notification.",
              example: "1",
            },
            {
              name: "SITE_MONITORING_UPTIME_HTTP_RETRIES",
              description: "In-probe HTTP retries for timeouts / 5xx / 429.",
              example: "3",
            },
            {
              name: "SITE_MONITORING_RUN_JOB_TRIES",
              description: "Queue retries when the run job throws (not when probe fails).",
              example: "3",
            },
          ]}
        />
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">4. Using the dashboard (daily)</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <strong className="text-foreground">Sign in</strong> — Email + password from your Laravel user. Seeded dev:{" "}
            <code className="rounded bg-muted px-1 text-xs">test@example.com</code> /{" "}
            <code className="rounded bg-muted px-1 text-xs">password</code> after <code className="rounded bg-muted px-1 text-xs">php artisan db:seed</code>.
          </li>
          <li>
            <strong className="text-foreground">Organization</strong> — Header org switcher sends{" "}
            <code className="rounded bg-muted px-1 text-xs">X-Organization-Id</code> on API calls. Pick the org you operate in.
          </li>
          <li>
            <strong className="text-foreground">Dashboard</strong> — Summary cards; links into vendors / invoices / monitoring / notifications.
          </li>
          <li>
            <strong className="text-foreground">Vendors / Invoices</strong> — List and manage records for the selected organization (requires API running).
          </li>
          <li>
            <strong className="text-foreground">Monitoring</strong> — List checks, <strong className="text-foreground">Run now</strong> queues one execution, view recent
            status. Scheduled runs require Horizon + scheduler (see Operations).
          </li>
          <li>
            <strong className="text-foreground">Notifications</strong> — In-app feed from the API.
          </li>
          <li>
            <strong className="text-foreground">Documentation</strong> — This sidebar section; no extra configuration.
          </li>
        </ul>
      </section>

      <section className="space-y-4">
        <h2 id="monitoring-configuration" className="scroll-mt-24 text-base font-semibold text-foreground">
          5. Configure monitoring checks
        </h2>
        <p className="text-muted-foreground">
          Create checks from the dashboard <strong className="text-foreground">Monitoring</strong> screen or via API.
          <code className="rounded bg-muted px-1 text-xs">interval_seconds</code> minimum <strong>60</strong>, maximum{" "}
          <strong>86400</strong>. Types <code className="rounded bg-muted px-1 text-xs">tcp</code>,{" "}
          <code className="rounded bg-muted px-1 text-xs">ping</code>,{" "}
          <code className="rounded bg-muted px-1 text-xs">dns</code>, <code className="rounded bg-muted px-1 text-xs">custom</code> are accepted by validation but not
          implemented yet (runs as unsupported).
        </p>
        <p className="text-xs text-muted-foreground">
          Local sample rows: run{" "}
          <code className="rounded bg-muted px-1 text-xs">php artisan db:seed --class=MonitoringDemoSeeder</code> (or{" "}
          <code className="rounded bg-muted px-1 text-xs">php artisan db:seed</code>) to add <code className="rounded bg-muted px-1 text-xs">[Demo]</code> checks on{" "}
          <strong className="text-foreground">Demo Organization</strong>.
        </p>

        <h3 className="text-sm font-semibold text-foreground">Uptime / HTTP / HTTPS</h3>
        <p className="text-xs text-muted-foreground">
          <code className="rounded bg-muted px-1">endpoint</code>: full URL or host path. <code className="rounded bg-muted px-1">configuration</code> (optional):
        </p>
        <pre className="overflow-x-auto rounded-lg border border-border/60 bg-muted/30 p-3 text-[11px] leading-relaxed text-foreground">
{`{
  "method": "HEAD",
  "timeout_seconds": 15,
  "expected_status": 200,
  "follow_redirects": true,
  "retry_attempts": 3,
  "retry_delay_ms": 400,
  "log_response_body": true,
  "log_response_body_max_bytes": 2048
}`}
        </pre>
        <p className="text-xs text-muted-foreground">
          Use <code className="rounded bg-muted px-1">GET</code> if you want a response body preview stored on logs. <code className="rounded bg-muted px-1">expected_status</code>{" "}
          can be a number or array of allowed codes.
        </p>

        <h3 className="pt-2 text-sm font-semibold text-foreground">SSL / TLS</h3>
        <p className="text-xs text-muted-foreground">
          <code className="rounded bg-muted px-1">endpoint</code>: <code className="rounded bg-muted px-1">https://host</code> or host. Optional{" "}
          <code className="rounded bg-muted px-1">configuration</code>:
        </p>
        <pre className="overflow-x-auto rounded-lg border border-border/60 bg-muted/30 p-3 text-[11px] leading-relaxed text-foreground">
{`{
  "port": 443,
  "timeout_seconds": 15,
  "verify_ssl": true,
  "connect_retries": 3,
  "connect_retry_delay_ms": 500
}`}
        </pre>

        <h3 className="pt-2 text-sm font-semibold text-foreground">Domain / WHOIS</h3>
        <p className="text-xs text-muted-foreground">
          <code className="rounded bg-muted px-1">endpoint</code>: domain or URL containing the registerable domain. Requires{" "}
          <code className="rounded bg-muted px-1">SITE_MONITORING_DOMAIN_PROVIDER=rdap</code> (default) for real expiry dates.
        </p>

        <h3 className="pt-2 text-sm font-semibold text-foreground">Example: create via curl</h3>
        <pre className="overflow-x-auto rounded-lg border border-border/60 bg-muted/30 p-3 text-[11px] leading-relaxed text-foreground">
{`curl -s -X POST "$API/api/v1/monitoring-checks" \\
  -H "Authorization: Bearer $TOKEN" \\
  -H "X-Organization-Id: $ORG_UUID" \\
  -H "Accept: application/json" -H "Content-Type: application/json" \\
  -d '{
    "name": "Homepage",
    "type": "https",
    "endpoint": "https://example.com",
    "interval_seconds": 300,
    "enabled": true,
    "configuration": { "method": "HEAD", "expected_status": 200 }
  }'`}
        </pre>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">6. Where to change behaviour in code</h2>
        <ul className="list-inside list-disc space-y-1 text-xs text-muted-foreground">
          <li>
            <code className="rounded bg-muted px-1">config/site-monitoring.php</code> — defaults; override with{" "}
            <code className="rounded bg-muted px-1">SITE_MONITORING_*</code> env vars.
          </li>
          <li>
            <code className="rounded bg-muted px-1">config/horizon.php</code> — worker queues, concurrency, timeouts.
          </li>
          <li>
            <code className="rounded bg-muted px-1">routes/console.php</code> — schedule frequency for dispatching due checks.
          </li>
          <li>
            <code className="rounded bg-muted px-1">app/SiteMonitoring/</code> — strategies, execution service, jobs, events, listeners.
          </li>
        </ul>
      </section>
    </article>
  );
}
