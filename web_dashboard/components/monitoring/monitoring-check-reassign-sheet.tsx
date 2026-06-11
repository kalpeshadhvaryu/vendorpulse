"use client";

import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { fetchOrganizations } from "@/lib/api/organizations";
import { getApiErrorMessage } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { reassignMonitoringCheck, type ReassignMonitoringCheckPayload } from "@/lib/api/monitoring";
import type { MonitoringCheck, MonitoringCheckReassignResult, Organization } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";

type Props = {
  check: MonitoringCheck | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function MonitoringCheckReassignSheet({ check, open, onOpenChange }: Props) {
  const queryClient = useQueryClient();
  const [toOrganizationId, setToOrganizationId] = useState<string>("");
  const [confirmName, setConfirmName] = useState("");
  const [preview, setPreview] = useState<MonitoringCheckReassignResult | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const organizationsQuery = useQuery({
    queryKey: queryKeys.organizations({ include_trashed: false }),
    queryFn: async () => (await fetchOrganizations({ include_trashed: false })).data,
    enabled: open,
  });

  const organizations = useMemo<Organization[]>(() => {
    const payload = organizationsQuery.data as unknown;

    if (Array.isArray(payload)) {
      return payload as Organization[];
    }

    if (payload && typeof payload === "object" && "data" in payload) {
      const nested = (payload as { data?: unknown }).data;
      if (Array.isArray(nested)) {
        return nested as Organization[];
      }
    }

    return [];
  }, [organizationsQuery.data]);

  const organizationOptions = useMemo(
    () => organizations.filter((org) => !check || org.id !== check.organization_id),
    [organizations, check],
  );

  useEffect(() => {
    if (!open) {
      setToOrganizationId("");
      setConfirmName("");
      setPreview(null);
      setSubmitError(null);
      return;
    }

    if (!toOrganizationId && organizationOptions.length > 0) {
      setToOrganizationId(organizationOptions[0].id);
    }
  }, [open, organizationOptions, toOrganizationId]);

  useEffect(() => {
    setPreview(null);
    setSubmitError(null);
  }, [toOrganizationId, check?.id]);

  const mutation = useMutation({
    mutationFn: async (payload: ReassignMonitoringCheckPayload) => {
      if (!check) throw new Error("No monitoring check selected.");
      return reassignMonitoringCheck(check.id, payload);
    },
    onError: (error) => {
      const message = getApiErrorMessage(error);
      setSubmitError(message);
      toast.error(message);
    },
  });

  function runDryPreview() {
    if (!check || !toOrganizationId) return;

    setSubmitError(null);

    mutation.mutate(
      {
        to_organization_id: toOrganizationId,
        execute: false,
      },
      {
        onSuccess: (result) => {
          setPreview(result);
          setSubmitError(null);
          toast.success("Reassign preview ready");
        },
      },
    );
  }

  function executeReassign() {
    if (!check || !toOrganizationId) return;

    setSubmitError(null);

    mutation.mutate(
      {
        to_organization_id: toOrganizationId,
        execute: true,
      },
      {
        onSuccess: (result) => {
          toast.success("Monitoring check moved", {
            description: `${result.check_name} moved to the selected organization.`,
          });
          void queryClient.invalidateQueries({ queryKey: ["monitoring-checks"] });
          void queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
          onOpenChange(false);
        },
      },
    );
  }

  const normalizedConfirmName = confirmName.trim().toLowerCase();
  const normalizedCheckName = (check?.name ?? "").trim().toLowerCase();
  const nameMatches = Boolean(check) && normalizedConfirmName.length > 0 && normalizedConfirmName === normalizedCheckName;

  const hasPreviewForSelection =
    Boolean(preview) &&
    preview?.mode === "dry-run" &&
    preview?.check_id === check?.id &&
    preview?.to_organization_id === toOrganizationId;

  const canExecute =
    Boolean(check) &&
    Boolean(toOrganizationId) &&
    nameMatches &&
    hasPreviewForSelection &&
    !mutation.isPending;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex h-auto w-full max-w-sm flex-col border-l p-0 sm:max-w-md">
        <SheetHeader className="border-b border-border/60 px-4 pb-3 pt-2 pr-10">
          <SheetTitle>Reassign monitoring check</SheetTitle>
        </SheetHeader>
        <div className="flex flex-col gap-4 p-4">
          {!check ? (
            <p className="text-sm text-muted-foreground">Select a check to reassign.</p>
          ) : (
            <>
              <div className="rounded-md border border-border/60 bg-muted/30 p-3 text-sm">
                <p className="font-medium text-foreground">{check.name}</p>
                <p className="text-muted-foreground">Current organization: {check.organization_id}</p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="target-organization">Target organization</Label>
                {organizationsQuery.isLoading ? (
                  <Skeleton className="h-9 w-full" />
                ) : (
                  <select
                    id="target-organization"
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={toOrganizationId}
                    onChange={(e) => setToOrganizationId(e.target.value)}
                    disabled={mutation.isPending || organizationOptions.length === 0}
                  >
                    {organizationOptions.length === 0 ? (
                      <option value="">No other organizations available</option>
                    ) : null}
                    {organizationOptions.map((org) => (
                      <option key={org.id} value={org.id}>
                        {org.name}
                      </option>
                    ))}
                  </select>
                )}
              </div>

              <div className="rounded-md border border-border/60 p-3 text-xs text-muted-foreground">
                Use Preview first. Execute runs in one backend transaction and updates the check plus related logs/social accounts.
              </div>

              {submitError ? (
                <div className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive">
                  {submitError}
                </div>
              ) : null}

              {preview ? (
                <div className="space-y-2 rounded-md border border-border/60 bg-muted/20 p-3 text-sm">
                  <p>
                    <span className="font-medium text-foreground">Logs to move:</span> {preview.monitoring_logs_to_move}
                  </p>
                  <p>
                    <span className="font-medium text-foreground">Social accounts to move:</span>{" "}
                    {preview.domain_social_accounts_to_move}
                  </p>
                </div>
              ) : null}

              <div className="space-y-2">
                <Label htmlFor="confirm-check-name">Type check name to confirm execute</Label>
                <Input
                  id="confirm-check-name"
                  value={confirmName}
                  onChange={(e) => setConfirmName(e.target.value)}
                  placeholder={check.name}
                  disabled={mutation.isPending}
                />
                <p className="text-xs text-muted-foreground">
                  Name match is case-insensitive. Run <strong className="text-foreground">Preview</strong> for the selected target first,
                  then Execute move is enabled.
                </p>
                {!hasPreviewForSelection ? (
                  <p className="text-xs text-amber-700">Preview is required for the current target organization.</p>
                ) : null}
                {!nameMatches && confirmName.trim().length > 0 ? (
                  <p className="text-xs text-amber-700">Entered name does not match this check.</p>
                ) : null}
              </div>

              <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                  Cancel
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  disabled={!check || !toOrganizationId || mutation.isPending}
                  onClick={runDryPreview}
                >
                  {mutation.isPending ? "Working..." : "Preview"}
                </Button>
                <Button type="button" disabled={!canExecute} onClick={executeReassign}>
                  Execute move
                </Button>
              </div>
            </>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
