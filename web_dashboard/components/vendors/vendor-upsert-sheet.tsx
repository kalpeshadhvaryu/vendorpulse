"use client";

import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import axios from "axios";
import { toast } from "sonner";
import { createVendor, updateVendor } from "@/lib/api/vendors";
import { createVendorEmail } from "@/lib/api/vendor-emails";
import { getApiErrorMessage } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import type { Vendor } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { VendorEmailAddressesSection } from "@/components/vendors/vendor-email-addresses-section";
import {
  VendorFormFields,
  emptyVendorFormDefaults,
  vendorFormSchema,
  vendorFormValuesToPayload,
  vendorToFormValues,
  type VendorFormValues,
} from "./vendor-form-fields";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  vendor: Vendor | null;
};

export function VendorUpsertSheet({ open, onOpenChange, vendor }: Props) {
  const queryClient = useQueryClient();
  const isEdit = Boolean(vendor);

  const form = useForm<VendorFormValues>({
    resolver: zodResolver(vendorFormSchema),
    defaultValues: emptyVendorFormDefaults,
  });

  const { reset, handleSubmit, formState } = form;

  useEffect(() => {
    if (!open) return;
    reset(vendor ? vendorToFormValues(vendor) : emptyVendorFormDefaults);
  }, [open, vendor, reset]);

  const mutation = useMutation({
    mutationFn: async (values: VendorFormValues) => {
      const payload = vendorFormValuesToPayload(values);
      if (vendor) {
        const v = await updateVendor(vendor.id, payload);
        return { vendor: v, inboundFailed: 0 };
      }
      const created = await createVendor(payload);
      const rows = values.inbound_emails.filter((r) => r.email.trim() !== "");
      let inboundFailed = 0;
      for (const row of rows) {
        try {
          await createVendorEmail({
            vendor_id: created.id,
            email: row.email.trim(),
            label: row.label.trim() === "" ? null : row.label.trim(),
            purpose: row.purpose,
          });
        } catch {
          inboundFailed += 1;
        }
      }
      return { vendor: created, inboundFailed };
    },
    onSuccess: ({ vendor: saved, inboundFailed }) => {
      toast.success(isEdit ? "Vendor updated" : "Vendor created");
      if (!isEdit && inboundFailed > 0) {
        toast.warning(
          `${inboundFailed} incoming address${inboundFailed === 1 ? "" : "es"} could not be saved. Add them from Edit vendor.`,
        );
      }
      void queryClient.invalidateQueries({ queryKey: ["vendors"] });
      if (saved?.id) {
        void queryClient.invalidateQueries({ queryKey: queryKeys.vendorEmails(saved.id) });
      }
      onOpenChange(false);
    },
    onError: (error) => {
      if (axios.isAxiosError(error)) {
        console.error("Backend Error:", error.response?.data);
      } else {
        console.error("Backend Error:", error);
      }

      const message = getApiErrorMessage(error);
      toast.error(message);
      alert(message || "Server error: Failed to create vendor.");
    },
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        className="flex h-full w-full max-w-lg flex-col border-l p-0 sm:max-w-2xl"
      >
        <SheetHeader className="shrink-0 border-b border-border/60 px-4 pb-3 pt-2 pr-10">
          <SheetTitle>{isEdit ? "Edit vendor" : "Add vendor"}</SheetTitle>
        </SheetHeader>
        <ScrollArea className="min-h-0 flex-1">
          <form
            id="vendor-upsert-form"
            className="flex flex-col gap-6 p-4"
            onSubmit={handleSubmit((v) => mutation.mutate(v))}
            noValidate
          >
            <VendorFormFields form={form} showInboundStaging={!vendor} />
          </form>

          {vendor?.id ? (
            <div className="border-t border-border/60 px-4 pb-4 pt-4">
              <VendorEmailAddressesSection vendorId={vendor.id} />
            </div>
          ) : null}

          <div className="flex flex-wrap justify-end gap-2 border-t border-border/60 p-4 pt-4">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button
              type="submit"
              form="vendor-upsert-form"
              disabled={mutation.isPending || formState.isSubmitting}
            >
              {mutation.isPending ? "Saving…" : isEdit ? "Save changes" : "Create vendor"}
            </Button>
          </div>
        </ScrollArea>
      </SheetContent>
    </Sheet>
  );
}
