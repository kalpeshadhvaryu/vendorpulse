"use client";

import { useEffect, useRef, useState } from "react";
import { fetchMe } from "@/lib/api/auth";
import { appPath } from "@/lib/app-path";
import { clearSessionCookie } from "@/lib/auth-cookie";
import { useAuthStore } from "@/stores/auth-store";

function redirectToLogin() {
  clearSessionCookie();
  if (typeof window !== "undefined") {
    window.location.assign(appPath("/login"));
  }
}

function pickOrganizationId(user: {
  default_organization_id: string | null;
  organizations?: Array<{ id: string }>;
}): string | null {
  const organizations = user.organizations ?? [];
  if (
    user.default_organization_id &&
    organizations.some((org) => org.id === user.default_organization_id)
  ) {
    return user.default_organization_id;
  }
  return organizations[0]?.id ?? null;
}

function hasWorkspaceAccess(
  token: string | null,
  user: ReturnType<typeof useAuthStore.getState>["user"],
  organizationId: string | null,
): boolean {
  if (!token || !user) {
    return false;
  }
  return Boolean(user.is_admin || organizationId);
}

export function AuthGuard({ children }: { children: React.ReactNode }) {
  const token = useAuthStore((s) => s.token);
  const user = useAuthStore((s) => s.user);
  const organizationId = useAuthStore((s) => s.organizationId);
  const setUser = useAuthStore((s) => s.setUser);
  const logout = useAuthStore((s) => s.logout);

  const [hydrated, setHydrated] = useState(false);
  const [statusMessage, setStatusMessage] = useState("Loading workspace…");
  const bootstrapStarted = useRef(false);

  const canRender = hasWorkspaceAccess(token, user, organizationId);

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
    if (!hydrated) {
      return;
    }

    if (!token) {
      setStatusMessage("Redirecting to sign in…");
      redirectToLogin();
      return;
    }

    if (user) {
      if (!hasWorkspaceAccess(token, user, organizationId)) {
        setStatusMessage("Redirecting to sign in…");
        void logout().finally(() => redirectToLogin());
      }
      return;
    }

    if (bootstrapStarted.current) {
      return;
    }
    bootstrapStarted.current = true;
    setStatusMessage("Restoring your session…");

    let cancelled = false;

    void (async () => {
      try {
        const me = await fetchMe();
        if (cancelled) {
          return;
        }
        setUser(me);
        const orgId = pickOrganizationId(me);
        if (!me.is_admin && !orgId) {
          setStatusMessage("Redirecting to sign in…");
          await logout();
          redirectToLogin();
          return;
        }
        if (!me.is_admin && !organizationId && orgId) {
          useAuthStore.getState().setOrganizationId(orgId);
        }
      } catch {
        if (cancelled) {
          return;
        }
        setStatusMessage("Redirecting to sign in…");
        await logout();
        redirectToLogin();
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [hydrated, token, user, organizationId, setUser, logout]);

  if (canRender) {
    return <>{children}</>;
  }

  return (
    <div className="flex min-h-dvh min-h-screen items-center justify-center bg-background text-muted-foreground">
      <div className="flex flex-col items-center gap-3">
        <div
          className="h-10 w-10 animate-spin rounded-full border-2 border-t-transparent"
          style={{ borderColor: "#4f46e5", borderTopColor: "transparent" }}
          aria-hidden
        />
        <p className="text-sm">{statusMessage}</p>
      </div>
    </div>
  );
}
