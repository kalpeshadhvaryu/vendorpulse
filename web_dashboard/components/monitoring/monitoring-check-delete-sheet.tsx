"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { deleteMonitoringCheck } from "@/lib/api/monitoring";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { MonitoringCheck } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";

type Props = {
  check: MonitoringCheck | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function MonitoringCheckDeleteSheet({ check, open, onOpenChange }: Props) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (id: string) => deleteMonitoringCheck(id),
    onSuccess: () => {
      toast.success("Check removed");
      void queryClient.invalidateQueries({ queryKey: ["monitoring-checks"] });
      onOpenChange(false);
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex h-auto w-full max-w-sm flex-col border-l p-0 sm:max-w-md">
        <SheetHeader className="border-b border-border/60 px-4 pb-3 pt-2 pr-10">
          <SheetTitle>Delete monitoring check</SheetTitle>
        </SheetHeader>
        <div className="flex flex-col gap-4 p-4">
          <p className="text-sm text-muted-foreground">
            {check ? (
              <>
                Remove <span className="font-medium text-foreground">{check.name}</span>? Run history for this check
                may be retained depending on API policy.
              </>
            ) : (
              "Select a check to delete."
            )}
          </p>
          <div className="flex flex-wrap justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={!check || mutation.isPending}
              onClick={() => check && mutation.mutate(check.id)}
            >
              {mutation.isPending ? "Deleting…" : "Delete"}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
