import Link from "next/link";

export default function DocsVendorsInvoicesPage() {
  return (
    <article className="space-y-10 text-sm leading-relaxed">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Vendors &amp; invoices</h1>
        <p className="mt-2 text-muted-foreground">
          Practical setup and daily workflow for vendor management and invoice tracking. This guide is organization-scoped
          and works with the same auth/context rules described in{" "}
          <Link href="/docs/configure" className="font-medium text-primary underline-offset-4 hover:underline">
            Configure &amp; use
          </Link>
          .
        </p>
      </div>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">1. Data model (quick mental map)</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>
            <strong className="text-foreground">Vendor</strong> = who provides the service (hosting, DNS, SaaS, telecom, etc.).
          </li>
          <li>
            <strong className="text-foreground">Invoice</strong> = bill/charge record tied to one vendor.
          </li>
          <li>
            <strong className="text-foreground">Relationship</strong> = one vendor can have many invoices.
          </li>
          <li>
            <strong className="text-foreground">Scope</strong> = both are filtered by selected organization (header context).
          </li>
        </ul>
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">2. One-time setup checklist</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>Sign in with a user that belongs to the target organization.</li>
          <li>Select that organization in the dashboard header.</li>
          <li>Open <strong className="text-foreground">Vendors</strong> and create at least one vendor.</li>
          <li>Open <strong className="text-foreground">Invoices</strong> and create an invoice linked to that vendor.</li>
          <li>Verify invoice due date and status fields so reminders/reporting are meaningful.</li>
        </ol>
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">3. Recommended vendor fields</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>Name and vendor type (hosting, DNS, SaaS, utility, etc.).</li>
          <li>Billing/support emails and website.</li>
          <li>Currency, billing cycle, expected amount, and renewal date.</li>
          <li>Status and notes for handover context.</li>
          <li>Optional email automation flags if your billing ingestion flow is enabled.</li>
        </ul>
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">4. Recommended invoice fields</h2>
        <ul className="list-inside list-disc space-y-2 text-muted-foreground">
          <li>Invoice number (unique per organization).</li>
          <li>Vendor link, amount, currency, and tax.</li>
          <li>Issued date, due date, and payment status.</li>
          <li>Description/metadata for searchability and audit trail.</li>
        </ul>
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">5. Daily operating flow</h2>
        <ol className="list-inside list-decimal space-y-2 text-muted-foreground">
          <li>Create/update vendor profiles as services change.</li>
          <li>Add invoices as soon as billing docs arrive.</li>
          <li>Move statuses from draft/pending to paid when payment clears.</li>
          <li>Review due dates weekly to avoid missed renewals.</li>
          <li>Use organization switcher to avoid editing data in the wrong tenant.</li>
        </ol>
      </section>

      <section className="space-y-4">
        <h2 className="text-base font-semibold text-foreground">6. Local demo records</h2>
        <p className="text-muted-foreground">
          Local/test environments can seed demo records named <strong className="text-foreground">Test Vendor</strong> and{" "}
          <strong className="text-foreground">TEST-INV-001</strong> for quick UI verification.
        </p>
        <ul className="list-inside list-disc space-y-1 text-xs text-muted-foreground">
          <li>Seeder class: database/seeders/VendorInvoiceDemoSeeder.php</li>
          <li>Runs through normal db:seed only in local/testing environments.</li>
          <li>Production is intentionally skipped by environment guard.</li>
        </ul>
      </section>
    </article>
  );
}
