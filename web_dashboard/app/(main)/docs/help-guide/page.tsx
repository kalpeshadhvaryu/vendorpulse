import Link from "next/link";

export default function DocsHelpGuidePage() {
  return (
    <article className="space-y-8 text-sm leading-relaxed">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Help guide</h1>
        <p className="mt-2 text-muted-foreground">
          Practical setup notes and troubleshooting for vendors, monitoring, and WHM or cPanel server analytics.
        </p>
      </div>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Quick setup checklist</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>Start API, Redis, Horizon, and scheduler. Monitoring jobs only run when workers are active.</li>
          <li>Sign in with a user that has an active organization and select the org in the top header.</li>
          <li>Create vendors first if you want monitors linked to a specific provider.</li>
          <li>Go to Monitoring and add checks. Use server type for WHM or cPanel integrations.</li>
        </ol>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">WHM and cPanel server monitor setup</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>
            In Monitoring, click Add check and choose type <strong className="text-foreground">server</strong>.
          </li>
          <li>
            Set Server type to <strong className="text-foreground">WHM</strong> or <strong className="text-foreground">cPanel</strong>. This auto-fills API path and metric mappings.
          </li>
          <li>
            For WHM: set API auth to <strong className="text-foreground">WHM token</strong>, base URL like <code className="rounded bg-muted px-1 text-xs">https://host:2087</code>, and provide WHM username plus API token.
          </li>
          <li>
            For cPanel: set API auth to <strong className="text-foreground">cPanel token</strong>, base URL like <code className="rounded bg-muted px-1 text-xs">https://host:2083</code>, and provide cPanel username plus API token.
          </li>
          <li>
            Optional: override metric paths if your API response keys differ. Keep defaults first, then adjust one path at a time.
          </li>
          <li>
            Save the check and use Run from the Monitoring table. Open History to see uptime state plus server analytics summary.
          </li>
        </ol>
      </section>

      <section id="seo-social-domain-setup" className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Add a domain for Social Media &amp; SEO</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>
            Open <strong className="text-foreground">Marketing &amp; SEO</strong> and go to <strong className="text-foreground">On-Page SEO Scorecard</strong>.
          </li>
          <li>
            Enter the full target URL you want to analyze, for example <code className="rounded bg-muted px-1 text-xs">https://example.com/page</code>.
          </li>
          <li>
            In the <strong className="text-foreground">Social Account Mapping</strong> section, the app reads the domain from that SEO URL and tries to match an existing monitoring check automatically.
          </li>
          <li>
            If a monitored domain already exists for that hostname, it will be selected automatically and social profiles will attach to it.
          </li>
          <li>
            If no monitored domain exists yet, click <strong className="text-foreground">Create domain from SEO URL</strong>. This creates a standard HTTP or HTTPS monitoring check using the URL origin.
          </li>
          <li>
            After the domain is bound, add your platform name, social handle/profile URL, and optional follower count, then save the mapping.
          </li>
          <li>
            Use the mapping table below the form to edit follower counts over time or remove outdated profile links.
          </li>
        </ol>
        <p className="text-muted-foreground">
          Recommendation: keep <strong className="text-foreground">domain monitoring</strong> and <strong className="text-foreground">social profile mapping</strong> separate. The domain is the monitored asset; social accounts are related marketing metadata attached to that asset.
        </p>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Common issues and fixes</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <strong className="text-foreground">Run queued but no result</strong>: ensure Horizon or queue worker is running and includes the site-monitoring queue.
          </li>
          <li>
            <strong className="text-foreground">401 or 403 from API</strong>: verify username and token format, then confirm token scope in WHM or cPanel.
          </li>
          <li>
            <strong className="text-foreground">Connection or SSL error</strong>: check firewall, open WHM or cPanel ports, and temporarily disable verify SSL only for testing.
          </li>
          <li>
            <strong className="text-foreground">No analytics values</strong>: API may respond with different JSON keys; update metrics paths in the server section.
          </li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">URL Checker safety note (VAPT &amp; Web Health)</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            URL Checker uses a <strong className="text-foreground">concurrency limit</strong> (max 5 parallel link requests)
            to reduce rate-limit triggers and avoid burst traffic.
          </li>
          <li>
            Requests send a standard <strong className="text-foreground">modern browser User-Agent</strong> and fallback
            from <code className="rounded bg-muted px-1 text-xs">HEAD</code> to lightweight <code className="rounded bg-muted px-1 text-xs">GET</code>
            when some firewalls reject HEAD checks.
          </li>
          <li>
            If a target still returns 403 or 429, treat it as server policy/rate limit and re-run later with fewer links.
          </li>
          <li>
            Use this feature only on domains you own or are authorized to test. It is designed for health checks,
            not aggressive probing or high-volume traffic.
          </li>
        </ul>
      </section>

      <section className="space-y-3">
        <h2 className="text-base font-semibold text-foreground">Where to go next</h2>
        <p className="text-muted-foreground">
          For full environment settings, see{" "}
          <Link href="/docs/configure" className="font-medium text-primary underline-offset-4 hover:underline">
            Configure &amp; use
          </Link>
          . For queue and scheduler operations, see{" "}
          <Link href="/docs/operations" className="font-medium text-primary underline-offset-4 hover:underline">
            Operations
          </Link>
          .
        </p>
      </section>
    </article>
  );
}
