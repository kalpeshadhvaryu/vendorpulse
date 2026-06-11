"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { deleteVendor } from "@/lib/api/vendors";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { Vendor } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";

type Props = {
  vendor: Vendor | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function VendorDeleteSheet({ vendor, open, onOpenChange }: Props) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (id: string) => deleteVendor(id),
    onSuccess: () => {
      toast.success("Vendor removed");
      void queryClient.invalidateQueries({ queryKey: ["vendors"] });
      onOpenChange(false);
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex h-auto w-full max-w-sm flex-col border-l p-0 sm:max-w-md">
        <SheetHeader className="border-b border-border/60 px-4 pb-3 pt-2 pr-10">
          <SheetTitle>Delete vendor</SheetTitle>
        </SheetHeader>
        <div className="flex flex-col gap-4 p-4">
          <p className="text-sm text-muted-foreground">
            {vendor ? (
              <>
                Remove <span className="font-medium text-foreground">{vendor.name}</span>? Invoices linked to this
                vendor may need to be reassigned in the API.
              </>
            ) : (
              "Select a vendor to delete."
            )}
          </p>
          <div className="flex flex-wrap justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={!vendor || mutation.isPending}
              onClick={() => vendor && mutation.mutate(vendor.id)}
            >
              {mutation.isPending ? "Deleting…" : "Delete"}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
