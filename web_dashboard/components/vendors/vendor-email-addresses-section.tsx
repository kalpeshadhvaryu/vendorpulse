"use client";

import Link from "next/link";
import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Trash2 } from "lucide-react";
import { toast } from "sonner";
import { createVendorEmail, deleteVendorEmail, fetchVendorEmails } from "@/lib/api/vendor-emails";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";

const PURPOSES = [
  { value: "general", label: "General" },
  { value: "primary", label: "Primary" },
  { value: "billing", label: "Billing" },
  { value: "alerts", label: "Alerts" },
  { value: "abuse", label: "Abuse" },
] as const;

type Props = {
  vendorId: string;
};

export function VendorEmailAddressesSection({ vendorId }: Props) {
  const queryClient = useQueryClient();
  const [email, setEmail] = useState("");
  const [label, setLabel] = useState("");
  const [purpose, setPurpose] = useState<string>("general");

  const listQuery = useQuery({
    queryKey: queryKeys.vendorEmails(vendorId),
    queryFn: () => fetchVendorEmails({ vendor_id: vendorId, per_page: 100 }),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createVendorEmail({
        vendor_id: vendorId,
        email: email.trim(),
        label: label.trim() === "" ? null : label.trim(),
        purpose,
      }),
    onSuccess: () => {
      toast.success("Address added");
      setEmail("");
      setLabel("");
      setPurpose("general");
      void queryClient.invalidateQueries({ queryKey: queryKeys.vendorEmails(vendorId) });
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteVendorEmail(id),
    onSuccess: () => {
      toast.success("Address removed");
      void queryClient.invalidateQueries({ queryKey: queryKeys.vendorEmails(vendorId) });
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  function addAddress(e: React.FormEvent) {
    e.preventDefault();
    if (!email.trim()) {
      toast.error("Enter an email address");
      return;
    }
    createMutation.mutate();
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-sm font-semibold">Incoming mail addresses</h3>
        <p className="mt-1 text-xs text-muted-foreground">
          Matching uses exact addresses on From, To, or Cc. There is no subject-line tag in this build—add each
          address that should route mail to this vendor (e.g. vendor senders and, if it appears on their mail, your org
          address for them). Optional domain matching is controlled by the vendor checkbox above. Org inboxes you poll
          are configured under{" "}
          <Link href="/settings/mailboxes" className="font-medium text-primary underline-offset-4 hover:underline">
            Settings → Email mailboxes
          </Link>{" "}
          (one mailbox per inbox when vendors use different organizational emails).
        </p>
      </div>

      <form className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end" onSubmit={addAddress}>
        <div className="min-w-0 flex-1 space-y-1.5">
          <Label htmlFor={`ve-email-${vendorId}`}>Email</Label>
          <Input
            id={`ve-email-${vendorId}`}
            type="email"
            placeholder="accounts@vendor.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoComplete="off"
          />
        </div>
        <div className="w-full space-y-1.5 sm:w-40">
          <Label htmlFor={`ve-label-${vendorId}`}>Label (optional)</Label>
          <Input
            id={`ve-label-${vendorId}`}
            placeholder="AP inbox"
            value={label}
            onChange={(e) => setLabel(e.target.value)}
          />
        </div>
        <div className="w-full space-y-1.5 sm:w-36">
          <Label htmlFor={`ve-purpose-${vendorId}`}>Purpose</Label>
          <select
            id={`ve-purpose-${vendorId}`}
            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            value={purpose}
            onChange={(e) => setPurpose(e.target.value)}
          >
            {PURPOSES.map((p) => (
              <option key={p.value} value={p.value}>
                {p.label}
              </option>
            ))}
          </select>
        </div>
        <Button type="submit" disabled={createMutation.isPending} className="sm:shrink-0">
          {createMutation.isPending ? "Adding…" : "Add address"}
        </Button>
      </form>

      {listQuery.isLoading ? (
        <Skeleton className="h-20 w-full" />
      ) : listQuery.isError ? (
        <p className="text-xs text-destructive">{getApiErrorMessage(listQuery.error)}</p>
      ) : listQuery.data?.items.length === 0 ? (
        <p className="text-xs text-muted-foreground">No addresses yet. Add at least one to match inbound mail.</p>
      ) : (
        <ul className="divide-y divide-border/60 rounded-md border border-border/60">
          {listQuery.data?.items.map((row) => (
            <li key={row.id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
              <div className="min-w-0">
                <p className="truncate font-mono text-xs">{row.email}</p>
                <p className="truncate text-xs text-muted-foreground">
                  {[row.label, row.purpose].filter(Boolean).join(" · ") || "—"}
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-8 w-8 shrink-0 text-destructive hover:text-destructive"
                aria-label="Remove address"
                disabled={deleteMutation.isPending}
                onClick={() => deleteMutation.mutate(row.id)}
              >
                <Trash2 className="size-4" />
              </Button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
