"use client";

import { Suspense, useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { Mail, Search } from "lucide-react";
import { EmailLogDetailSheet } from "@/components/email-activity/email-log-detail-sheet";
import { fetchEmailLogs } from "@/lib/api/email-logs";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

const selectClass = cn(
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-sm",
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
  "disabled:cursor-not-allowed disabled:opacity-50",
);

const STATUS_OPTIONS = [
  { value: "all", label: "All statuses" },
  { value: "pending", label: "Pending" },
  { value: "normalized", label: "Normalized" },
  { value: "matched", label: "Matched" },
  { value: "extracted", label: "Extracted" },
  { value: "completed", label: "Completed" },
  { value: "failed", label: "Failed" },
];

function outcomeVariant(kind: string): "default" | "success" | "warning" | "secondary" {
  if (kind === "invoice_created" || kind === "invoice_updated") return "success";
  if (kind === "skipped") return "warning";
  return "secondary";
}

function EmailActivityPageInner() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const debouncedSearch = useDebouncedValue(search, 350);

  const selectedLogId = searchParams?.get("log") ?? null;
  const [detailOpen, setDetailOpen] = useState(Boolean(selectedLogId));

  useEffect(() => {
    setDetailOpen(Boolean(selectedLogId));
  }, [selectedLogId]);

  const query = useQuery({
    queryKey: queryKeys.emailLogs({
      search: debouncedSearch,
      processing_status: status === "all" ? "" : status,
      per_page: "50",
    }),
    queryFn: () =>
      fetchEmailLogs({
        search: debouncedSearch || undefined,
        processing_status: status === "all" ? undefined : status,
        per_page: 50,
      }),
  });

  function openLog(id: string) {
    const params = new URLSearchParams(searchParams?.toString() ?? "");
    params.set("log", id);
    router.push(`/email-activity?${params.toString()}`);
  }

  function closeDetail(open: boolean) {
    if (!open) {
      const params = new URLSearchParams(searchParams?.toString() ?? "");
      params.delete("log");
      const qs = params.toString();
      router.push(qs ? `/email-activity?${qs}` : "/email-activity");
    }
    setDetailOpen(open);
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Email activity</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Inbound messages processed by mailbox polling — vendor match, extraction, and invoice automation outcomes.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <Mail className="h-5 w-5" />
              Processed emails
            </CardTitle>
            <CardDescription>
              Click a row for subject, body preview, and extraction details. Invoice automation requires{" "}
              <code className="rounded bg-muted px-1 text-xs">EMAIL_MONITORING_INVOICE_AUTOMATION_ENABLED</code>.
            </CardDescription>
          </div>
          <div className="flex w-full flex-col gap-3 sm:max-w-md sm:flex-row">
            <div className="relative w-full">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search subject, sender…"
                className="pl-9"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <select
              className={cn(selectClass, "sm:w-44")}
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              aria-label="Processing status filter"
            >
              {STATUS_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>
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
                  <TableHead>Received</TableHead>
                  <TableHead>Subject</TableHead>
                  <TableHead>From</TableHead>
                  <TableHead>Vendor</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Invoice outcome</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {query.data?.items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                      No processed emails yet. Configure a mailbox under Settings and ensure polling runs.
                    </TableCell>
                  </TableRow>
                ) : (
                  query.data?.items.map((log) => (
                    <TableRow
                      key={log.id}
                      className="cursor-pointer"
                      onClick={() => openLog(log.id)}
                    >
                      <TableCell className="whitespace-nowrap text-muted-foreground">
                        {log.received_at ? new Date(log.received_at).toLocaleString() : "—"}
                      </TableCell>
                      <TableCell className="max-w-[240px] truncate font-medium">
                        {log.subject ?? "(no subject)"}
                      </TableCell>
                      <TableCell className="max-w-[180px] truncate text-muted-foreground">
                        {log.from_email ?? "—"}
                      </TableCell>
                      <TableCell>{log.vendor_name ?? "—"}</TableCell>
                      <TableCell>
                        <Badge variant="outline">{log.processing_status}</Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant={outcomeVariant(log.invoice_outcome.kind)}>
                          {log.invoice_outcome.label}
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <EmailLogDetailSheet
        logId={selectedLogId}
        open={detailOpen}
        onOpenChange={closeDetail}
      />
    </div>
  );
}

export default function EmailActivityPage() {
  return (
    <Suspense fallback={<Skeleton className="mx-auto h-64 max-w-7xl w-full" />}>
      <EmailActivityPageInner />
    </Suspense>
  );
}
