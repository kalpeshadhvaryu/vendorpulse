import Link from "next/link";

export default function DocsApiPage() {
  return (
    <article className="space-y-8 text-sm leading-relaxed">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">API reference</h1>
        <p className="mt-2 text-muted-foreground">
          REST API version <strong className="text-foreground">v1</strong>, JSON responses, Laravel Sanctum personal
          access tokens. Practical configuration (CORS, env) lives in{" "}
          <Link href="/docs/configure" className="font-medium text-primary underline-offset-4 hover:underline">
            Configure &amp; use
          </Link>
          .
        </p>
      </div>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Base URL</h2>
        <p className="text-muted-foreground">
          All routes are prefixed with <code className="rounded bg-muted px-1 text-xs">/api/v1</code>. Example:{" "}
          <code className="rounded bg-muted px-1 text-xs">http://127.0.0.1:8000/api/v1/auth/login</code>.
        </p>
        <p className="text-muted-foreground">
          The dashboard reads <code className="rounded bg-muted px-1 text-xs">NEXT_PUBLIC_API_URL</code> (no trailing
          slash after <code className="rounded bg-muted px-1 text-xs">v1</code>).
        </p>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Headers (authenticated calls)</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <code className="rounded bg-muted px-1 text-xs">Accept: application/json</code>
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">Content-Type: application/json</code> (for POST/PATCH)
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">Authorization: Bearer {'{token}'}</code>
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">X-Organization-Id: {'{organization_uuid}'}</code> on org-scoped routes
          </li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Auth</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <code className="rounded bg-muted px-1 text-xs">POST /auth/register</code> — name, email, password,
            password_confirmation, organization_name.
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">POST /auth/login</code> — email, password, optional{" "}
            <code className="rounded bg-muted px-1 text-xs">device_name</code>.
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">POST /auth/logout</code> and{" "}
            <code className="rounded bg-muted px-1 text-xs">GET /auth/me</code> — require bearer token.
          </li>
        </ul>
        <h3 className="pt-2 text-sm font-semibold text-foreground">Example: login</h3>
        <pre className="overflow-x-auto rounded-lg border border-border/60 bg-muted/30 p-3 text-[11px] leading-relaxed text-foreground">
{`curl -s -X POST "http://127.0.0.1:8000/api/v1/auth/login" \\
  -H "Accept: application/json" -H "Content-Type: application/json" \\
  -d '{"email":"test@example.com","password":"password","device_name":"cli"}'`}
        </pre>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">CORS & Sanctum</h2>
        <p className="text-muted-foreground">
          Laravel <code className="rounded bg-muted px-1 text-xs">config/cors.php</code> uses{" "}
          <code className="rounded bg-muted px-1 text-xs">CORS_ALLOWED_ORIGINS</code>. Include every origin you use for
          the dashboard. Align <code className="rounded bg-muted px-1 text-xs">SANCTUM_STATEFUL_DOMAINS</code> with the
          dashboard host (no scheme, include port).
        </p>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Email mailboxes (API)</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <code className="rounded bg-muted px-1 text-xs">GET/POST/PATCH/DELETE …/email-mailboxes</code> — CRUD
            org-scoped mailboxes (<code className="rounded bg-muted px-1 text-xs">driver</code>:{" "}
            <code className="rounded bg-muted px-1 text-xs">imap</code> or{" "}
            <code className="rounded bg-muted px-1 text-xs">gmail_api</code>). Responses redact{" "}
            <code className="rounded bg-muted px-1 text-xs">password</code>,{" "}
            <code className="rounded bg-muted px-1 text-xs">client_secret</code>, and{" "}
            <code className="rounded bg-muted px-1 text-xs">refresh_token</code>; use{" "}
            <code className="rounded bg-muted px-1 text-xs">has_password</code>,{" "}
            <code className="rounded bg-muted px-1 text-xs">has_client_secret</code>,{" "}
            <code className="rounded bg-muted px-1 text-xs">has_refresh_token</code> to know if values exist.
          </li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Monitoring (API)</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <code className="rounded bg-muted px-1 text-xs">GET/POST/PATCH/DELETE …/monitoring-checks</code> — CRUD
            checks (type, endpoint, interval, configuration). Payload examples:{" "}
            <Link href="/docs/configure" className="font-medium text-primary underline-offset-4 hover:underline">
              Configure &amp; use §5
            </Link>
            .
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">POST …/monitoring-checks/{'{id}'}/run</code> — enqueue a
            single run (requires workers).
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">GET …/monitoring-checks/{'{id}'}/logs</code> — paginated
            probe history (status, HTTP code, timing, meta). Query:{" "}
            <code className="rounded bg-muted px-1 text-xs">from</code>,{" "}
            <code className="rounded bg-muted px-1 text-xs">to</code>,{" "}
            <code className="rounded bg-muted px-1 text-xs">status</code> (ok / failed / error / degraded / skipped),{" "}
            <code className="rounded bg-muted px-1 text-xs">per_page</code>, <code className="rounded bg-muted px-1 text-xs">page</code>.
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">GET …/monitoring-checks/{'{id}'}/log-summary</code> — time in
            state and uptime ratio for a window. Query: <code className="rounded bg-muted px-1 text-xs">from</code>,{" "}
            <code className="rounded bg-muted px-1 text-xs">to</code> (optional; default last 7 days).
          </li>
        </ul>
      </section>
    </article>
  );
}
