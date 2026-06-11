"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Loader2, MoreHorizontal, Plus } from "lucide-react";
import { toast } from "sonner";
import { fetchEmailMailboxes, testEmailMailboxConnection } from "@/lib/api/email-mailboxes";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { EmailMailbox } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { EmailMailboxDeleteSheet } from "@/components/email-mailboxes/email-mailbox-delete-sheet";
import { EmailMailboxUpsertSheet } from "@/components/email-mailboxes/email-mailbox-upsert-sheet";

function driverLabel(driver: string): string {
  if (driver === "gmail_api") return "Gmail API";
  if (driver === "imap") return "IMAP";
  return driver;
}

function configSummary(m: EmailMailbox): string {
  const c = m.connection_config ?? {};
  if (m.driver === "imap") {
    const host = (c.host as string) || "—";
    return host;
  }
  const id = (c.client_id as string) || "";
  if (!id) return "—";
  return id.length > 36 ? `${id.slice(0, 18)}…` : id;
}

export default function SettingsMailboxesPage() {
  const perPage = 50;
  const queryClient = useQueryClient();

  const [upsertOpen, setUpsertOpen] = useState(false);
  const [testingMailboxId, setTestingMailboxId] = useState<string | null>(null);
  const [upsertMailbox, setUpsertMailbox] = useState<EmailMailbox | null>(null);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleteMailbox, setDeleteMailbox] = useState<EmailMailbox | null>(null);

  const query = useQuery({
    queryKey: queryKeys.emailMailboxes({ per_page: String(perPage) }),
    queryFn: () => fetchEmailMailboxes({ per_page: perPage }),
  });

  const testConnectionMutation = useMutation({
    mutationFn: ({ id, organizationId }: { id: string; organizationId: string }) =>
      testEmailMailboxConnection(id, organizationId),
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.emailMailboxes() });
      toast.success(result.message || "Incoming mailbox connection successful.");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
    onSettled: () => setTestingMailboxId(null),
  });

  function runConnectionTest(mailbox: EmailMailbox) {
    setTestingMailboxId(mailbox.id);
    testConnectionMutation.mutate({ id: mailbox.id, organizationId: mailbox.organization_id });
  }

  function openCreate() {
    setUpsertMailbox(null);
    setUpsertOpen(true);
  }

  function openEdit(m: EmailMailbox) {
    setUpsertMailbox(m);
    setUpsertOpen(true);
  }

  function openDelete(m: EmailMailbox) {
    setDeleteMailbox(m);
    setDeleteOpen(true);
  }

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-6">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Button variant="ghost" size="sm" className="-ml-2 mb-1 h-8 gap-1 px-2 text-muted-foreground" asChild>
            <Link href="/settings">
              <ArrowLeft className="h-4 w-4" />
              Settings
            </Link>
          </Button>
          <h1 className="text-2xl font-semibold tracking-tight">Email mailboxes</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Org-level inboxes the app connects to and reads. Use <strong className="text-foreground">Test incoming connection</strong>{" "}
            to verify IMAP credentials before waiting for the scheduler. Matching each message to a vendor still happens on
            the vendor screen. Polling needs queue workers and a working IMAP connector.
          </p>
        </div>
        <Button type="button" className="shrink-0 self-start sm:self-auto" onClick={openCreate}>
          <Plus className="size-4" />
          Add mailbox
        </Button>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Mailboxes</CardTitle>
          <CardDescription>
            Rows are scoped by <code className="rounded bg-muted px-1 text-xs">X-Organization-Id</code>. Passwords and
            OAuth secrets are write-only in the API response.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {query.isLoading ? (
            <Skeleton className="h-48 w-full" />
          ) : query.isError ? (
            <p className="text-sm text-destructive">{getApiErrorMessage(query.error)}</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Driver</TableHead>
                  <TableHead>Endpoint</TableHead>
                  <TableHead>Enabled</TableHead>
                  <TableHead>Last polled</TableHead>
                  <TableHead>Connection</TableHead>
                  <TableHead className="w-[52px] text-right" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {query.data?.items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center text-sm text-muted-foreground">
                      No mailboxes yet. Use <strong className="text-foreground">Add mailbox</strong> to store IMAP or
                      Gmail API credentials.
                    </TableCell>
                  </TableRow>
                ) : (
                  query.data?.items.map((m) => (
                    <TableRow key={m.id}>
                      <TableCell className="font-medium">{m.name}</TableCell>
                      <TableCell className="text-muted-foreground">{driverLabel(m.driver)}</TableCell>
                      <TableCell className="max-w-[220px] truncate font-mono text-xs text-muted-foreground">
                        {configSummary(m)}
                      </TableCell>
                      <TableCell>
                        <Badge variant={m.is_enabled ? "success" : "secondary"}>{m.is_enabled ? "On" : "Off"}</Badge>
                      </TableCell>
                      <TableCell className="text-muted-foreground text-sm">
                        {m.last_polled_at ? new Date(m.last_polled_at).toLocaleString() : "—"}
                      </TableCell>
                      <TableCell className="max-w-[200px]">
                        {m.last_error ? (
                          <span className="line-clamp-2 text-xs text-destructive" title={m.last_error}>
                            {m.last_error}
                          </span>
                        ) : m.last_successful_sync_at ? (
                          <span className="text-xs text-emerald-600">OK</span>
                        ) : (
                          <span className="text-xs text-muted-foreground">Not tested</span>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                              <span className="sr-only">Open menu</span>
                              <MoreHorizontal className="size-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem
                              disabled={testingMailboxId === m.id}
                              onClick={() => runConnectionTest(m)}
                            >
                              {testingMailboxId === m.id ? (
                                <span className="flex items-center gap-2">
                                  <Loader2 className="size-4 animate-spin" />
                                  Testing…
                                </span>
                              ) : (
                                "Test incoming connection"
                              )}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => openEdit(m)}>Edit</DropdownMenuItem>
                            <DropdownMenuItem
                              className="text-destructive focus:text-destructive"
                              onClick={() => openDelete(m)}
                            >
                              Delete
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          )}
          {query.data?.items.some((m) => m.last_error) ? (
            <p className="mt-4 text-xs text-muted-foreground">
              Connection errors appear in the <span className="font-medium text-foreground">Connection</span> column. Use
              test again after fixing credentials. IMAP requires <code className="rounded bg-muted px-1">ext-imap</code> on
              the API server.
            </p>
          ) : null}
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Many vendors, many org emails</CardTitle>
          <CardDescription>Concrete setup so fetch and routing line up.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3 text-sm leading-relaxed text-muted-foreground">
          <ol className="list-inside list-decimal space-y-2">
            <li>
              <strong className="text-foreground">Mailboxes (this page)</strong> — Create{" "}
              <strong className="text-foreground">one enabled mailbox per inbox</strong> you need to read (each set of
              IMAP/Gmail credentials). The scheduler dispatches a poll job for{" "}
              <strong className="text-foreground">every</strong> enabled mailbox in this organization.
            </li>
            <li>
              <strong className="text-foreground">Vendors</strong> — For each vendor, under{" "}
              <strong className="text-foreground">Incoming mail addresses</strong>, add every address that should attach
              mail to that vendor: senders you invoice with, and—if it appears on their threads—your org address for
              that vendor. Matching uses exact From / To / Cc (plus optional domain rule on the vendor).
            </li>
            <li>
              Mail is <strong className="text-foreground">fetched</strong> from the mailboxes here, then{" "}
              <strong className="text-foreground">attributed</strong> using the vendor lists. The two lists do not have
              to be identical strings, but anything you need to match must appear on the message headers and on the
              vendor list.
            </li>
          </ol>
        </CardContent>
      </Card>

      <EmailMailboxUpsertSheet
        key={upsertMailbox?.id ?? "create"}
        open={upsertOpen}
        onOpenChange={setUpsertOpen}
        mailbox={upsertMailbox}
      />

      <EmailMailboxDeleteSheet
        mailbox={deleteMailbox}
        open={deleteOpen}
        onOpenChange={(open) => {
          setDeleteOpen(open);
          if (!open) setDeleteMailbox(null);
        }}
      />
    </div>
  );
}
