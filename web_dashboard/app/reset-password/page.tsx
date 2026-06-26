"use client";

import Link from "next/link";
import { Suspense, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";
import { resetPasswordRequest } from "@/lib/api/auth";
import { getApiErrorMessage } from "@/lib/api/errors";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ThemeToggle } from "@/components/layout/theme-toggle";

const schema = z
  .object({
    email: z.string().email("Enter a valid email."),
    password: z.string().min(8, "Password must be at least 8 characters."),
    password_confirmation: z.string().min(8, "Confirm your password."),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: "Passwords do not match.",
    path: ["password_confirmation"],
  });

type FormValues = z.infer<typeof schema>;

function ResetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams?.get("token") ?? "";
  const emailFromQuery = searchParams?.get("email") ?? "";

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      email: emailFromQuery,
      password: "",
      password_confirmation: "",
    },
  });

  useEffect(() => {
    if (emailFromQuery) {
      form.setValue("email", emailFromQuery);
    }
  }, [emailFromQuery, form]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) =>
      resetPasswordRequest({
        email: values.email,
        token,
        password: values.password,
        password_confirmation: values.password_confirmation,
      }),
    onSuccess: (message) => {
      toast.success(message);
      router.replace("/login");
    },
    onError: (err) => {
      toast.error(getApiErrorMessage(err));
    },
  });

  const missingToken = token.trim() === "";

  return (
    <Card className="w-full max-w-md border-border/60 shadow-lg">
      <CardHeader>
        <CardTitle>Reset password</CardTitle>
        <CardDescription>Choose a new password for your VendorPulse account.</CardDescription>
      </CardHeader>
      <CardContent>
        {missingToken ? (
          <div className="space-y-4 text-sm text-muted-foreground">
            <p>This reset link is invalid or incomplete. Request a new link from the forgot password page.</p>
            <Button asChild className="w-full">
              <Link href="/forgot-password">Request reset link</Link>
            </Button>
          </div>
        ) : (
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
              <Label htmlFor="password">New password</Label>
              <Input id="password" type="password" autoComplete="new-password" {...form.register("password")} />
              {form.formState.errors.password ? (
                <p className="text-xs text-destructive">{form.formState.errors.password.message}</p>
              ) : null}
            </div>
            <div className="space-y-2">
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input
                id="password_confirmation"
                type="password"
                autoComplete="new-password"
                {...form.register("password_confirmation")}
              />
              {form.formState.errors.password_confirmation ? (
                <p className="text-xs text-destructive">{form.formState.errors.password_confirmation.message}</p>
              ) : null}
            </div>
            <Button type="submit" className="w-full" disabled={mutation.isPending}>
              {mutation.isPending ? "Saving…" : "Update password"}
            </Button>
          </form>
        )}
        <p className="mt-4 text-center text-sm">
          <Link href="/login" className="text-primary hover:underline">
            Back to sign in
          </Link>
        </p>
      </CardContent>
    </Card>
  );
}

export default function ResetPasswordPage() {
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
        </div>
        <Suspense
          fallback={
            <Card className="w-full max-w-md border-border/60 shadow-lg">
              <CardContent className="py-10 text-center text-sm text-muted-foreground">Loading…</CardContent>
            </Card>
          }
        >
          <ResetPasswordForm />
        </Suspense>
      </div>
    </div>
  );
}
