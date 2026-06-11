import Link from "next/link";

export default function DocsGettingStartedPage() {
  return (
    <article className="space-y-8 text-sm leading-relaxed">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Getting started</h1>
        <p className="mt-2 text-muted-foreground">
          Run the Laravel API and this dashboard on your machine. For env variables, monitoring JSON, and how to use
          each page, read{" "}
          <Link href="/docs/configure" className="font-medium text-primary underline-offset-4 hover:underline">
            Configure &amp; use
          </Link>
          .
        </p>
      </div>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">1. Laravel API</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>
            Copy <code className="rounded bg-muted px-1 text-xs">.env.example</code> to <code className="rounded bg-muted px-1 text-xs">.env</code> and set{" "}
            <code className="rounded bg-muted px-1 text-xs">APP_KEY</code>, database, and{" "}
            <code className="rounded bg-muted px-1 text-xs">QUEUE_CONNECTION=redis</code> for Horizon.
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">php artisan migrate</code> then{" "}
            <code className="rounded bg-muted px-1 text-xs">php artisan db:seed</code> for the dev user (
            <code className="rounded bg-muted px-1 text-xs">test@example.com</code> /{" "}
            <code className="rounded bg-muted px-1 text-xs">password</code>).
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">php artisan serve</code> — default{" "}
            <code className="rounded bg-muted px-1 text-xs">http://127.0.0.1:8000</code>.
          </li>
        </ol>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">2. Web dashboard</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>
            <code className="rounded bg-muted px-1 text-xs">cd web_dashboard</code> then{" "}
            <code className="rounded bg-muted px-1 text-xs">npm install</code>.
          </li>
          <li>
            Create <code className="rounded bg-muted px-1 text-xs">.env.local</code> from{" "}
            <code className="rounded bg-muted px-1 text-xs">.env.local.example</code>. Set{" "}
            <code className="rounded bg-muted px-1 text-xs">NEXT_PUBLIC_API_URL</code> to match how you open the API (
            prefer <code className="rounded bg-muted px-1 text-xs">http://127.0.0.1:8000/api/v1</code> if you use{" "}
            <code className="rounded bg-muted px-1 text-xs">127.0.0.1</code> for the browser).
          </li>
          <li>
            <code className="rounded bg-muted px-1 text-xs">npm run dev</code> — default{" "}
            <code className="rounded bg-muted px-1 text-xs">http://localhost:3000</code> (or use the same host style as
            the API URL).
          </li>
        </ol>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">3. Workers (monitoring & mail)</h2>
        <p className="text-muted-foreground">
          For queued monitoring and email jobs, run Redis, <code className="rounded bg-muted px-1 text-xs">php artisan horizon</code>, and a scheduler (
          <code className="rounded bg-muted px-1 text-xs">php artisan schedule:work</code> or OS cron). Details in{" "}
          <Link href="/docs/operations" className="font-medium text-primary underline-offset-4 hover:underline">
            Operations
          </Link>
          .
        </p>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">4. Sign in</h2>
        <p className="text-muted-foreground">
          Use an API user with at least one organization. After seeding, use{" "}
          <code className="rounded bg-muted px-1 text-xs">test@example.com</code> /{" "}
          <code className="rounded bg-muted px-1 text-xs">password</code>. Register additional users via{" "}
          <code className="rounded bg-muted px-1 text-xs">POST /api/v1/auth/register</code> (see{" "}
          <Link href="/docs/api" className="font-medium text-primary underline-offset-4 hover:underline">
            API reference
          </Link>
          ).
        </p>
      </section>
    </article>
  );
}
