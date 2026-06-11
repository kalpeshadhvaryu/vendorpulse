import Link from "next/link";

export default function DocsOperationsPage() {
  return (
    <article className="space-y-8 text-sm leading-relaxed">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Operations</h1>
        <p className="mt-2 text-muted-foreground">
          How to run processes in production-like setups. Environment variable tables:{" "}
          <Link href="/docs/configure" className="font-medium text-primary underline-offset-4 hover:underline">
            Configure &amp; use
          </Link>
          .
        </p>
      </div>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Processes to run</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <code className="rounded bg-muted px-1 text-xs">php artisan horizon</code> — Redis-backed workers. Must listen on queues listed in{" "}
            <code className="rounded bg-muted px-1 text-xs">config/horizon.php</code> (includes <code className="rounded bg-muted px-1 text-xs">default</code> and{" "}
            <code className="rounded bg-muted px-1 text-xs">site-monitoring</code>).
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">php artisan schedule:work</code> — long-running scheduler in dev; or install cron:{" "}
            <code className="rounded bg-muted px-1 text-xs">* * * * * cd /path/to/app && php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code>{" "}
            (see <code className="rounded bg-muted px-1 text-xs">deploy/schedule-run.cron.example</code>).
          </li>
          <li>
            <strong className="text-foreground">Docker Compose</strong> — the repo&apos;s <code className="rounded bg-muted px-1 text-xs">docker-compose.yml</code> includes a{" "}
            <code className="rounded bg-muted px-1 text-xs">scheduler</code> service running <code className="rounded bg-muted px-1 text-xs">php artisan schedule:work</code>{" "}
            alongside <code className="rounded bg-muted px-1 text-xs">app</code> and <code className="rounded bg-muted px-1 text-xs">horizon</code>.
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">php artisan serve</code> or PHP-FPM/nginx serving <code className="rounded bg-muted px-1 text-xs">public/</code>.
          </li>
          <li>
            <strong className="text-foreground">Redis</strong> must be up before Horizon starts.
          </li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Config cache</h2>
        <p className="text-muted-foreground">
          After changing <code className="rounded bg-muted px-1 text-xs">.env</code> on a server:{" "}
          <code className="rounded bg-muted px-1 text-xs">php artisan config:clear</code> (or <code className="rounded bg-muted px-1 text-xs">config:cache</code> in production
          deploy scripts).
        </p>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Auth / API connectivity</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            Match <code className="rounded bg-muted px-1 text-xs">NEXT_PUBLIC_API_URL</code> to the host the browser can reach (often <code className="rounded bg-muted px-1 text-xs">127.0.0.1</code> instead of <code className="rounded bg-muted px-1 text-xs">localhost</code>).
          </li>
          <li>
            Reset dev password: <code className="rounded bg-muted px-1 text-xs">php artisan db:seed</code>. Verify DB:{" "}
            <code className="rounded bg-muted px-1 text-xs">php artisan vendorpulse:verify-dev-login</code>.
          </li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Frontend (Next.js)</h2>
        <p className="text-muted-foreground">
          Run from <code className="rounded bg-muted px-1 text-xs">web_dashboard/</code>. Restart <code className="rounded bg-muted px-1 text-xs">npm run dev</code> after any{" "}
          <code className="rounded bg-muted px-1 text-xs">.env.local</code> change. Repo root <code className="rounded bg-muted px-1 text-xs">npm run dev</code> runs Laravel Vite,
          not the dashboard — use <code className="rounded bg-muted px-1 text-xs">npm run dev:dashboard</code> from the repo root if that script exists in root{" "}
          <code className="rounded bg-muted px-1 text-xs">package.json</code>.
        </p>
      </section>
    </article>
  );
}
