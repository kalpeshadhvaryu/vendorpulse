"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { fetchEmailLog } from "@/lib/api/email-logs";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";

type Props = {
  logId: string | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function outcomeVariant(kind: string): "default" | "success" | "warning" | "secondary" {
  if (kind === "invoice_created" || kind === "invoice_updated") return "success";
  if (kind === "skipped") return "warning";
  return "secondary";
}

export function EmailLogDetailSheet({ logId, open, onOpenChange }: Props) {
  const query = useQuery({
    queryKey: queryKeys.emailLog(logId ?? ""),
    queryFn: () => fetchEmailLog(logId!),
    enabled: open && Boolean(logId),
  });

  const log = query.data;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>Email activity</SheetTitle>
        </SheetHeader>

        {query.isLoading ? (
          <Skeleton className="h-48 w-full" />
        ) : query.isError ? (
          <p className="text-sm text-destructive">{getApiErrorMessage(query.error)}</p>
        ) : log ? (
          <div className="flex flex-col gap-4 text-sm">
            <div>
              <p className="font-medium">{log.subject ?? "(no subject)"}</p>
              <p className="mt-1 text-muted-foreground">
                From {log.from_email ?? "—"}
                {log.received_at ? ` · ${new Date(log.received_at).toLocaleString()}` : ""}
              </p>
              {log.mailbox_name ? (
                <p className="mt-1 text-muted-foreground">Mailbox: {log.mailbox_name}</p>
              ) : null}
              {log.vendor_name ? (
                <p className="mt-1 text-muted-foreground">Vendor: {log.vendor_name}</p>
              ) : null}
            </div>

            <div className="flex flex-wrap gap-2">
              <Badge variant="outline">{log.processing_status}</Badge>
              <Badge variant={outcomeVariant(log.invoice_outcome.kind)}>
                {log.invoice_outcome.label}
              </Badge>
            </div>

            {log.invoice_outcome.invoice_id ? (
              <div>
                <p className="text-muted-foreground">Linked invoice</p>
                <Button variant="link" className="h-auto p-0" asChild>
                  <Link href="/invoices">#{log.invoice_outcome.invoice_number ?? "View invoices"}</Link>
                </Button>
              </div>
            ) : null}

            {log.failure_reason ? (
              <p className="text-destructive">Failure: {log.failure_reason}</p>
            ) : null}

            {log.latest_extraction ? (
              <>
                <Separator />
                <div>
                  <p className="font-medium">Latest extraction</p>
                  <p className="mt-1 text-muted-foreground">
                    Status {log.latest_extraction.status}
                    {log.latest_extraction.aggregate_confidence != null
                      ? ` · confidence ${(log.latest_extraction.aggregate_confidence * 100).toFixed(0)}%`
                      : ""}
                  </p>
                  {log.latest_extraction.candidate_invoice_number ? (
                    <p className="mt-1">Candidate number: {log.latest_extraction.candidate_invoice_number}</p>
                  ) : null}
                  {log.latest_extraction.candidate_event ? (
                    <p className="mt-1">Event: {log.latest_extraction.candidate_event}</p>
                  ) : null}
                </div>
              </>
            ) : null}

            {log.body_text_preview ? (
              <>
                <Separator />
                <div>
                  <p className="font-medium">Message preview</p>
                  <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-md border bg-muted/30 p-3 text-xs">
                    {log.body_text_preview}
                  </pre>
                </div>
              </>
            ) : null}
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
