"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { deleteEmailMailbox } from "@/lib/api/email-mailboxes";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { EmailMailbox } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";

type Props = {
  mailbox: EmailMailbox | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function EmailMailboxDeleteSheet({ mailbox, open, onOpenChange }: Props) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (id: string) => deleteEmailMailbox(id),
    onSuccess: () => {
      toast.success("Mailbox removed");
      void queryClient.invalidateQueries({ queryKey: ["email-mailboxes"] });
      onOpenChange(false);
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex h-auto w-full max-w-sm flex-col border-l p-0 sm:max-w-md">
        <SheetHeader className="border-b border-border/60 px-4 pb-3 pt-2 pr-10">
          <SheetTitle>Delete mailbox</SheetTitle>
        </SheetHeader>
        <div className="flex flex-col gap-4 p-4">
          <p className="text-sm text-muted-foreground">
            {mailbox ? (
              <>
                Remove <span className="font-medium text-foreground">{mailbox.name}</span>? Stored credentials are
                deleted with the row.
              </>
            ) : (
              "Select a mailbox to delete."
            )}
          </p>
          <div className="flex flex-wrap justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={!mailbox || mutation.isPending}
              onClick={() => mailbox && mutation.mutate(mailbox.id)}
            >
              {mutation.isPending ? "Deleting…" : "Delete"}
            </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
