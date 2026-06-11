"use client";

import { useEffect, useMemo } from "react";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Loader2 } from "lucide-react";
import { toast } from "sonner";
import { z } from "zod";
import {
  EMAIL_MAILBOX_DRIVERS,
  createEmailMailbox,
  testEmailMailboxConnection,
  updateEmailMailbox,
} from "@/lib/api/email-mailboxes";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { EmailMailbox } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";

const selectClass = cn(
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-sm",
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
);

function buildSchema(isEdit: boolean, mailbox: EmailMailbox | null) {
  return z
    .object({
      name: z.string().min(1, "Name is required").max(255),
      driver: z.enum(EMAIL_MAILBOX_DRIVERS),
      is_enabled: z.boolean(),
      host: z.string(),
      port: z.coerce.number().int().min(1).max(65535),
      encryption: z.enum(["ssl", "tls", "none"]),
      username: z.string(),
      password: z.string(),
      folder: z.string(),
      client_id: z.string(),
      client_secret: z.string(),
      refresh_token: z.string(),
    })
    .superRefine((data, ctx) => {
      if (data.driver === "imap") {
        if (!data.host.trim()) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Host is required.", path: ["host"] });
        }
        if (!data.username.trim()) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Username is required.", path: ["username"] });
        }
        const pwd = data.password.trim();
        if (!isEdit && !pwd) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Password is required.", path: ["password"] });
        }
        if (isEdit && !pwd && !mailbox?.has_password) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: "Set a password or leave an existing one from a previous save.",
            path: ["password"],
          });
        }
      } else {
        if (!data.client_id.trim()) {
          ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Client ID is required.", path: ["client_id"] });
        }
        const sec = data.client_secret.trim();
        const rt = data.refresh_token.trim();
        if (!isEdit) {
          if (!sec) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: "Client secret is required.",
              path: ["client_secret"],
            });
          }
          if (!rt) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: "Refresh token is required.",
              path: ["refresh_token"],
            });
          }
        } else {
          if (!sec && !mailbox?.has_client_secret) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: "Client secret is missing; paste a new one or keep an existing stored value.",
              path: ["client_secret"],
            });
          }
          if (!rt && !mailbox?.has_refresh_token) {
            ctx.addIssue({
              code: z.ZodIssueCode.custom,
              message: "Refresh token is missing; paste a new one or keep an existing stored value.",
              path: ["refresh_token"],
            });
          }
        }
      }
    });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

const emptyDefaults: FormValues = {
  name: "",
  driver: "imap",
  is_enabled: true,
  host: "",
  port: 993,
  encryption: "ssl",
  username: "",
  password: "",
  folder: "INBOX",
  client_id: "",
  client_secret: "",
  refresh_token: "",
};

function mailboxToFormValues(m: EmailMailbox): FormValues {
  const c = m.connection_config ?? {};
  return {
    name: m.name,
    driver: m.driver,
    is_enabled: m.is_enabled,
    host: (c.host as string) ?? "",
    port: typeof c.port === "number" ? c.port : 993,
    encryption: (c.encryption === "tls" || c.encryption === "none" ? c.encryption : "ssl") as "ssl" | "tls" | "none",
    username: (c.username as string) ?? "",
    password: "",
    folder: (c.folder as string) ?? "INBOX",
    client_id: (c.client_id as string) ?? "",
    client_secret: "",
    refresh_token: "",
  };
}

function valuesToCreatePayload(values: FormValues) {
  if (values.driver === "imap") {
    return {
      name: values.name.trim(),
      driver: values.driver,
      is_enabled: values.is_enabled,
      connection_config: {
        host: values.host.trim(),
        port: values.port,
        encryption: values.encryption,
        username: values.username.trim(),
        password: values.password.trim(),
        folder: values.folder.trim() || "INBOX",
      },
    };
  }
  return {
    name: values.name.trim(),
    driver: values.driver,
    is_enabled: values.is_enabled,
    connection_config: {
      client_id: values.client_id.trim(),
      client_secret: values.client_secret.trim(),
      refresh_token: values.refresh_token.trim(),
    },
  };
}

function valuesToUpdatePayload(values: FormValues) {
  const base: Record<string, unknown> = {
    name: values.name.trim(),
    driver: values.driver,
    is_enabled: values.is_enabled,
  };

  if (values.driver === "imap") {
    const cfg: Record<string, unknown> = {
      host: values.host.trim(),
      port: values.port,
      encryption: values.encryption,
      username: values.username.trim(),
      folder: values.folder.trim() || "INBOX",
    };
    const pwd = values.password.trim();
    if (pwd) {
      cfg.password = pwd;
    }
    base.connection_config = cfg;
    return base;
  }

  const cfg: Record<string, unknown> = {
    client_id: values.client_id.trim(),
  };
  const sec = values.client_secret.trim();
  const rt = values.refresh_token.trim();
  if (sec) {
    cfg.client_secret = sec;
  }
  if (rt) {
    cfg.refresh_token = rt;
  }
  base.connection_config = cfg;
  return base;
}

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mailbox: EmailMailbox | null;
};

export function EmailMailboxUpsertSheet({ open, onOpenChange, mailbox }: Props) {
  const queryClient = useQueryClient();
  const isEdit = Boolean(mailbox);

  const schema = useMemo(() => buildSchema(isEdit, mailbox), [isEdit, mailbox]);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyDefaults,
  });

  const { reset, handleSubmit, register, watch, control, formState } = form;
  const driver = watch("driver");

  useEffect(() => {
    if (!open) return;
    reset(mailbox ? mailboxToFormValues(mailbox) : emptyDefaults);
  }, [open, mailbox, reset]);

  const mutation = useMutation({
    mutationFn: async (values: FormValues) => {
      if (mailbox) {
        return updateEmailMailbox(mailbox.id, valuesToUpdatePayload(values));
      }
      return createEmailMailbox(valuesToCreatePayload(values));
    },
    onSuccess: () => {
      toast.success(isEdit ? "Mailbox updated" : "Mailbox created");
      void queryClient.invalidateQueries({ queryKey: queryKeys.emailMailboxes() });
      onOpenChange(false);
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  const testConnectionMutation = useMutation({
    mutationFn: () => {
      if (!mailbox) {
        throw new Error("Save the mailbox before testing the connection.");
      }
      return testEmailMailboxConnection(mailbox.id, mailbox.organization_id);
    },
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.emailMailboxes() });
      toast.success(result.message || "Incoming mailbox connection successful.");
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  const handleTestConnection = handleSubmit(async (values) => {
    if (!mailbox) {
      return;
    }

    try {
      await updateEmailMailbox(mailbox.id, valuesToUpdatePayload(values));
      await queryClient.invalidateQueries({ queryKey: queryKeys.emailMailboxes() });
      testConnectionMutation.mutate();
    } catch (e) {
      toast.error(getApiErrorMessage(e));
    }
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex h-full w-full max-w-lg flex-col border-l p-0 sm:max-w-xl">
        <SheetHeader className="shrink-0 border-b border-border/60 px-4 pb-3 pt-2 pr-10">
          <SheetTitle>{isEdit ? "Edit mailbox" : "Add mailbox"}</SheetTitle>
        </SheetHeader>
        <ScrollArea className="min-h-0 flex-1">
          <form
            key={mailbox?.id ?? "new"}
            className="flex flex-col gap-4 p-4"
            onSubmit={handleSubmit((v) => mutation.mutate(v))}
            noValidate
          >
            <p className="text-xs text-muted-foreground leading-relaxed">
              Org-wide inbox used when email monitoring polls (IMAP or Gmail API). Secrets are stored server-side; the
              API never returns them. Polling still depends on workers and a finished connector implementation.
            </p>

            <div className="space-y-2">
              <Label htmlFor="mb-name">Name</Label>
              <Input id="mb-name" placeholder="Accounts payable" autoComplete="off" {...register("name")} />
              {formState.errors.name ? (
                <p className="text-xs text-destructive">{formState.errors.name.message}</p>
              ) : null}
            </div>

            <div className="space-y-2">
              <Label htmlFor="mb-driver">Driver</Label>
              <select id="mb-driver" className={selectClass} {...register("driver")}>
                <option value="imap">IMAP</option>
                <option value="gmail_api">Gmail API</option>
              </select>
            </div>

            <div className="rounded-md border border-border/60 px-3 py-3">
              <div className="flex items-start gap-2">
                <Controller
                  name="is_enabled"
                  control={control}
                  render={({ field }) => (
                    <input
                      type="checkbox"
                      id="mb-enabled"
                      className="mt-0.5 size-4 shrink-0 rounded border-input"
                      checked={field.value}
                      onChange={(e) => field.onChange(e.target.checked)}
                    />
                  )}
                />
                <div className="min-w-0">
                  <Label htmlFor="mb-enabled" className="font-medium">
                    Enabled
                  </Label>
                  <p className="text-xs text-muted-foreground">Disabled mailboxes are skipped by the scheduler.</p>
                </div>
              </div>
            </div>

            {driver === "imap" ? (
              <>
                <div className="space-y-2">
                  <Label htmlFor="mb-host">IMAP host</Label>
                  <Input id="mb-host" placeholder="imap.gmail.com" autoComplete="off" {...register("host")} />
                  {formState.errors.host ? (
                    <p className="text-xs text-destructive">{formState.errors.host.message}</p>
                  ) : null}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="mb-port">Port</Label>
                    <Input id="mb-port" type="number" {...register("port")} />
                    {formState.errors.port ? (
                      <p className="text-xs text-destructive">{formState.errors.port.message}</p>
                    ) : null}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="mb-enc">Encryption</Label>
                    <select id="mb-enc" className={selectClass} {...register("encryption")}>
                      <option value="ssl">SSL</option>
                      <option value="tls">TLS</option>
                      <option value="none">None</option>
                    </select>
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mb-user">Username</Label>
                  <Input id="mb-user" autoComplete="off" {...register("username")} />
                  {formState.errors.username ? (
                    <p className="text-xs text-destructive">{formState.errors.username.message}</p>
                  ) : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mb-pass">Password</Label>
                  <Input id="mb-pass" type="password" autoComplete="new-password" {...register("password")} />
                  {isEdit && mailbox?.has_password ? (
                    <p className="text-xs text-muted-foreground">Leave blank to keep the current password.</p>
                  ) : null}
                  {formState.errors.password ? (
                    <p className="text-xs text-destructive">{formState.errors.password.message}</p>
                  ) : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mb-folder">Folder</Label>
                  <Input id="mb-folder" placeholder="INBOX" {...register("folder")} />
                </div>
              </>
            ) : (
              <>
                <div className="space-y-2">
                  <Label htmlFor="mb-cid">OAuth client ID</Label>
                  <Input id="mb-cid" autoComplete="off" {...register("client_id")} />
                  {formState.errors.client_id ? (
                    <p className="text-xs text-destructive">{formState.errors.client_id.message}</p>
                  ) : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mb-csec">Client secret</Label>
                  <Input id="mb-csec" type="password" autoComplete="new-password" {...register("client_secret")} />
                  {isEdit && mailbox?.has_client_secret ? (
                    <p className="text-xs text-muted-foreground">Leave blank to keep the current client secret.</p>
                  ) : null}
                  {formState.errors.client_secret ? (
                    <p className="text-xs text-destructive">{formState.errors.client_secret.message}</p>
                  ) : null}
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mb-rt">Refresh token</Label>
                  <Input id="mb-rt" type="password" autoComplete="new-password" {...register("refresh_token")} />
                  {isEdit && mailbox?.has_refresh_token ? (
                    <p className="text-xs text-muted-foreground">Leave blank to keep the current refresh token.</p>
                  ) : null}
                  {formState.errors.refresh_token ? (
                    <p className="text-xs text-destructive">{formState.errors.refresh_token.message}</p>
                  ) : null}
                </div>
              </>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-4">
              {isEdit ? (
                <Button
                  type="button"
                  variant="secondary"
                  disabled={mutation.isPending || testConnectionMutation.isPending}
                  onClick={() => void handleTestConnection()}
                >
                  {testConnectionMutation.isPending ? (
                    <>
                      <Loader2 className="mr-2 size-4 animate-spin" />
                      Testing…
                    </>
                  ) : (
                    "Test incoming connection"
                  )}
                </Button>
              ) : (
                <p className="text-xs text-muted-foreground">Save the mailbox first, then test the connection.</p>
              )}
              <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                  Cancel
                </Button>
                <Button type="submit" disabled={mutation.isPending || testConnectionMutation.isPending}>
                  {mutation.isPending ? "Saving…" : isEdit ? "Save" : "Create"}
                </Button>
              </div>
            </div>
          </form>
        </ScrollArea>
      </SheetContent>
    </Sheet>
  );
}
