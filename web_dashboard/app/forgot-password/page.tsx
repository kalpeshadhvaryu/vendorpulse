"use client";

import Link from "next/link";
import { useForm } from "react-hook-form";
import { z } from "zod";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";
import { forgotPasswordRequest } from "@/lib/api/auth";
import { getApiErrorMessage } from "@/lib/api/errors";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ThemeToggle } from "@/components/layout/theme-toggle";

const schema = z.object({
  email: z.string().email("Enter a valid email."),
});

type FormValues = z.infer<typeof schema>;

export default function ForgotPasswordPage() {
  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { email: "" },
  });

  const mutation = useMutation({
    mutationFn: (values: FormValues) => forgotPasswordRequest(values.email),
    onSuccess: (message) => {
      toast.success(message);
      form.reset({ email: form.getValues("email") });
    },
    onError: (err) => {
      toast.error(getApiErrorMessage(err));
    },
  });

  const submitted = mutation.isSuccess;

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
        <Card className="w-full max-w-md border-border/60 shadow-lg">
          <CardHeader>
            <CardTitle>Forgot password</CardTitle>
            <CardDescription>
              Enter your account email. If it exists, we will send a reset link valid for 60 minutes.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {submitted ? (
              <div className="space-y-4 text-sm text-muted-foreground">
                <p>
                  Check your inbox for <strong className="text-foreground">{form.getValues("email")}</strong>. The link
                  opens a page where you can choose a new password.
                </p>
                <p>If you do not see the email, check spam or ask your administrator to verify SMTP settings.</p>
                <Button type="button" variant="outline" className="w-full" onClick={() => mutation.reset()}>
                  Send again
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
                <Button type="submit" className="w-full" disabled={mutation.isPending}>
                  {mutation.isPending ? "Sending…" : "Send reset link"}
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
      </div>
    </div>
  );
}
