"use client";

import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { z } from "zod";
import type { ExperienceMonitoringTest } from "@/lib/api/types";
import {
  createExperienceMonitoringTest,
  updateExperienceMonitoringTest,
} from "@/lib/api/experience-monitoring";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import { DEFAULT_EXPERIENCE_MONITORING_BROWSER } from "@/lib/experience-monitoring/browsers";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";

const schema = z.object({
  name: z.string().min(1, "Test name is required").max(255),
  login_url: z.string().url("Enter a valid login URL"),
  login_username: z.string().min(1, "Username/email is required").max(255),
  login_password: z.string().max(2048),
  dashboard_url: z.string().url("Enter a valid dashboard URL"),
  interval_seconds: z.coerce.number().int().min(60).max(86400),
  timeout_ms: z.coerce.number().int().min(1000).max(180000),
  concurrent_sessions: z.coerce.number().int().min(1).max(25).nullable(),
  enabled: z.boolean(),
});

type FormValues = z.infer<typeof schema>;

const defaults: FormValues = {
  name: "",
  login_url: "",
  login_username: "",
  login_password: "",
  dashboard_url: "",
  interval_seconds: 300,
  timeout_ms: 30000,
  concurrent_sessions: 1,
  enabled: true,
};

function toValues(test: ExperienceMonitoringTest): FormValues {
  return {
    name: test.name,
    login_url: test.login_url,
    login_username: test.login_username,
    login_password: "",
    dashboard_url: test.dashboard_url,
    interval_seconds: test.interval_seconds,
    timeout_ms: test.timeout_ms,
    concurrent_sessions: test.concurrent_sessions ?? 1,
    enabled: test.enabled,
  };
}

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  test: ExperienceMonitoringTest | null;
};

export function ExperienceMonitoringUpsertSheet({ open, onOpenChange, test }: Props) {
  const queryClient = useQueryClient();
  const isEdit = Boolean(test);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: defaults,
  });

  useEffect(() => {
    if (!open) return;
    form.reset(test ? toValues(test) : defaults);
  }, [open, test, form]);

  const mutation = useMutation({
    mutationFn: async (values: FormValues) => {
      const payload = {
        ...values,
        browser_type: DEFAULT_EXPERIENCE_MONITORING_BROWSER,
        login_password: values.login_password.trim() || undefined,
      };

      if (isEdit && test) {
        return updateExperienceMonitoringTest(test.id, payload);
      }

      return createExperienceMonitoringTest(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? "Experience monitoring test updated" : "Experience monitoring test created");
      void queryClient.invalidateQueries({ queryKey: queryKeys.experienceMonitoringTests({}) });
      onOpenChange(false);
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full max-w-xl overflow-y-auto p-0">
        <SheetHeader className="border-b border-border/60 px-4 pb-3 pt-3">
          <SheetTitle>{isEdit ? "Edit experience test" : "Create experience test"}</SheetTitle>
        </SheetHeader>

        <form
          className="space-y-4 p-4"
          onSubmit={form.handleSubmit((values) => {
            mutation.mutate(values);
          })}
        >
          <div className="space-y-2">
            <Label htmlFor="name">Test Name</Label>
            <Input id="name" {...form.register("name")} />
            <p className="text-xs text-destructive">{form.formState.errors.name?.message}</p>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="login_url">Login URL</Label>
              <Input id="login_url" {...form.register("login_url")} />
              <p className="text-xs text-destructive">{form.formState.errors.login_url?.message}</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="dashboard_url">Dashboard URL</Label>
              <Input id="dashboard_url" {...form.register("dashboard_url")} />
              <p className="text-xs text-destructive">{form.formState.errors.dashboard_url?.message}</p>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="login_username">Email / Username</Label>
              <Input id="login_username" {...form.register("login_username")} />
              <p className="text-xs text-destructive">{form.formState.errors.login_username?.message}</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="login_password">Password {isEdit ? "(leave empty to keep)" : ""}</Label>
              <Input id="login_password" type="password" {...form.register("login_password")} />
              <p className="text-xs text-destructive">{form.formState.errors.login_password?.message}</p>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="interval_seconds">Check Interval (seconds)</Label>
              <Input id="interval_seconds" type="number" min={60} step={60} {...form.register("interval_seconds")} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="timeout_ms">Timeout (ms)</Label>
              <Input id="timeout_ms" type="number" min={1000} step={500} {...form.register("timeout_ms")} />
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="concurrent_sessions">Concurrent Sessions (optional)</Label>
              <Input
                id="concurrent_sessions"
                type="number"
                min={1}
                step={1}
                {...form.register("concurrent_sessions", {
                  setValueAs: (v) => {
                    if (v === "" || v == null) return null;
                    const n = Number(v);
                    return Number.isFinite(n) ? n : null;
                  },
                })}
              />
            </div>
          </div>

          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="h-4 w-4" {...form.register("enabled")} />
            Enabled
          </label>

          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? "Saving..." : isEdit ? "Update" : "Create"}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
