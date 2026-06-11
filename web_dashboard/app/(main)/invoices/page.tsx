"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { fetchInvoices } from "@/lib/api/invoices";
import { queryKeys } from "@/lib/api/query-keys";
import { formatMoney } from "@/lib/format";
import { getApiErrorMessage } from "@/lib/api/errors";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { useDashboardSettings } from "@/stores/dashboard-settings-store";

export default function InvoicesPage() {
  const invoicesPerPage = useDashboardSettings((s) => s.invoicesPerPage);
  const query = useQuery({
    queryKey: queryKeys.invoices({ per_page: String(invoicesPerPage) }),
    queryFn: () => fetchInvoices({ per_page: invoicesPerPage }),
  });

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Invoices</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Invoice register from the Laravel API. Rows linked to inbound email show source metadata.
        </p>
      </div>
      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Ledger</CardTitle>
          <CardDescription>Amounts shown in minor units converted for display.</CardDescription>
        </CardHeader>
        <CardContent>
          {query.isLoading ? (
            <Skeleton className="h-64 w-full" />
          ) : query.isError ? (
            <p className="text-sm text-destructive">{getApiErrorMessage(query.error)}</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Number</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Due</TableHead>
                  <TableHead>Source</TableHead>
                  <TableHead>Paid</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {query.data?.items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                      No invoices yet.
                    </TableCell>
                  </TableRow>
                ) : (
                  query.data?.items.map((inv) => (
                    <TableRow key={inv.id}>
                      <TableCell className="font-medium">{inv.number}</TableCell>
                      <TableCell>
                        <Badge variant={inv.paid_at ? "success" : "warning"}>{inv.status}</Badge>
                      </TableCell>
                      <TableCell>{formatMoney(inv.amount_cents, inv.currency)}</TableCell>
                      <TableCell>{inv.due_on ?? "—"}</TableCell>
                      <TableCell className="max-w-[220px]">
                        {inv.email_source ? (
                          <div className="flex flex-col gap-0.5">
                            <Badge variant="secondary" className="w-fit">
                              {inv.email_source.auto_generated ? "From email" : "Email linked"}
                            </Badge>
                            {inv.email_source.subject ? (
                              <Link
                                href={`/email-activity?log=${inv.email_source.email_log_id}`}
                                className="truncate text-xs text-primary hover:underline"
                                title={inv.email_source.subject}
                              >
                                {inv.email_source.subject}
                              </Link>
                            ) : (
                              <Link
                                href={`/email-activity?log=${inv.email_source.email_log_id}`}
                                className="text-xs text-primary hover:underline"
                              >
                                View email
                              </Link>
                            )}
                          </div>
                        ) : (
                          <span className="text-sm text-muted-foreground">Manual</span>
                        )}
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {inv.paid_at ? new Date(inv.paid_at).toLocaleDateString() : "—"}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
