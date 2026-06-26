"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";
import { loginRequest } from "@/lib/api/auth";
import { publicApiBaseUrl, publicApiUrlFromEnv } from "@/lib/api/client";
import { getApiErrorMessage } from "@/lib/api/errors";
import { useAuthStore } from "@/stores/auth-store";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ThemeToggle } from "@/components/layout/theme-toggle";

const schema = z.object({
  email: z.string().email("Enter a valid email."),
  password: z.string().min(1, "Password is required."),
});

type FormValues = z.infer<typeof schema>;

export default function LoginPage() {
  const router = useRouter();
  const setSession = useAuthStore((s) => s.setSession);
  const token = useAuthStore((s) => s.token);
  const organizationId = useAuthStore((s) => s.organizationId);
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    const unsub = useAuthStore.persist.onFinishHydration(() => setHydrated(true));
    if (useAuthStore.persist.hasHydrated()) {
      setHydrated(true);
    }
    const failSafe = window.setTimeout(() => setHydrated(true), 2500);
    return () => {
      unsub?.();
      window.clearTimeout(failSafe);
    };
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    if (token && (organizationId || isAdmin)) {
      router.replace("/dashboard");
    }
  }, [hydrated, token, organizationId, isAdmin, router]);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", password: "" },
  });

  const mutation = useMutation({
    mutationFn: (values: FormValues) => loginRequest({ ...values, device_name: "VendorPulse Web" }),
    onSuccess: ({ token, user }) => {
      setSession({ token, user });
      toast.success("Signed in");
      router.replace("/dashboard");
    },
    onError: (err) => {
      toast.error(getApiErrorMessage(err));
    },
  });

  return (
    <div className="relative flex min-h-screen flex-col bg-gradient-to-br from-background via-background to-primary/5">
      <div className="absolute right-4 top-4">
        <ThemeToggle />
      </div>
      <div className="flex flex-1 flex-col items-center justify-center px-4 py-16">
        <div className="mb-10 text-center">
          <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary text-lg font-bold text-primary-foreground shadow-lg shadow-primary/25">
            VP
          </div>
          <h1 className="text-2xl font-semibold tracking-tight">VendorPulse</h1>
          <p className="mt-2 max-w-md text-sm text-muted-foreground">
            Infrastructure operations for vendors, invoices, renewals, and monitoring.
          </p>
        </div>
        <Card className="w-full max-w-md border-border/60 shadow-lg">
          <CardHeader>
            <CardTitle>Sign in</CardTitle>
            <CardDescription>Use the same credentials as the Laravel API (Sanctum token).</CardDescription>
          </CardHeader>
          <CardContent>
            <form
              className="space-y-4"
              onSubmit={form.handleSubmit((values) => {
                mutation.mutate(values);
              })}
            >
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input id="email" type="email" autoComplete="email" {...form.register("email")} />
                {form.formState.errors.email ? (
                  <p className="text-xs text-destructive">{form.formState.errors.email.message}</p>
                ) : null}
              </div>
              <div className="space-y-2">
                <div className="flex items-center justify-between gap-2">
                  <Label htmlFor="password">Password</Label>
                  <Link href="/forgot-password" className="text-xs text-primary hover:underline">
                    Forgot password?
                  </Link>
                </div>
                <Input id="password" type="password" autoComplete="current-password" {...form.register("password")} />
                {form.formState.errors.password ? (
                  <p className="text-xs text-destructive">{form.formState.errors.password.message}</p>
                ) : null}
              </div>
              <Button type="submit" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending ? "Signing in…" : "Continue"}
              </Button>
            </form>
            <p className="mt-4 text-center text-xs text-muted-foreground">
              API:{" "}
              <code className="rounded bg-muted px-1 break-all">{publicApiBaseUrl}</code>
              {!publicApiUrlFromEnv ? (
                <span className="mt-1 block text-[0.65rem] leading-snug opacity-80">
                  Using built-in default. Override with <code className="rounded bg-muted/80 px-0.5">NEXT_PUBLIC_API_URL</code>{" "}
                  in <code className="rounded bg-muted/80 px-0.5">.env.local</code>, then restart{" "}
                  <code className="rounded bg-muted/80 px-0.5">npm run dev</code>.
                </span>
              ) : null}
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
