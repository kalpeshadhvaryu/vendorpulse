"use client";

import Link from "next/link";
import { ShieldAlert } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { useAuthStore } from "@/stores/auth-store";

export function AdminAccessGate({ children }: { children: React.ReactNode }) {
  const isAdmin = Boolean(useAuthStore((s) => s.user?.is_admin));

  if (!isAdmin) {
    return (
      <div className="mx-auto flex max-w-4xl flex-col gap-6">
        <Card className="border-border/60">
          <CardContent className="flex flex-col items-center justify-center gap-4 py-12 text-center">
            <ShieldAlert className="h-12 w-12 text-muted-foreground/50" />
            <p className="text-sm text-muted-foreground">
              Platform administration is limited to global admins.
            </p>
            <Button variant="secondary" size="sm" asChild>
              <Link href="/dashboard">Back to dashboard</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return <>{children}</>;
}
