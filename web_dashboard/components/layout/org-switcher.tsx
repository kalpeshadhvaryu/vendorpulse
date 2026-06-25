"use client";

import { useMemo, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Building2, Check, Loader2, Search } from "lucide-react";
import { fetchOrganizations } from "@/lib/api/organizations";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { Organization } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { useAuthStore } from "@/stores/auth-store";

function filterOrganizations(organizations: Organization[], query: string): Organization[] {
  const q = query.trim().toLowerCase();
  if (!q) {
    return organizations;
  }

  return organizations.filter((org) => {
    const haystack = `${org.name} ${org.slug ?? ""} ${org.id}`.toLowerCase();
    return haystack.includes(q);
  });
}

export function OrgSwitcher() {
  const queryClient = useQueryClient();
  const token = useAuthStore((s) => s.token);
  const organizationId = useAuthStore((s) => s.organizationId);
  const isAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const memberOrganizations = useAuthStore((s) => s.user?.organizations ?? []);
  const setOrganizationId = useAuthStore((s) => s.setOrganizationId);

  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");

  const organizationsQuery = useQuery({
    queryKey: queryKeys.organizations({ include_trashed: false }),
    queryFn: () => fetchOrganizations({ include_trashed: false }),
    enabled: Boolean(token) && isAdmin,
    staleTime: 60_000,
  });

  const organizations = isAdmin ? (organizationsQuery.data?.data ?? []) : memberOrganizations;

  const filtered = useMemo(
    () => filterOrganizations(organizations, search),
    [organizations, search],
  );

  const current = organizations.find((o) => o.id === organizationId);

  const isLoading = isAdmin && organizationsQuery.isLoading;
  const loadError = isAdmin && organizationsQuery.isError ? organizationsQuery.error : null;

  if (!isAdmin && memberOrganizations.length === 0) {
    return (
      <div className="rounded-md border border-dashed border-border/80 px-3 py-1.5 text-xs text-muted-foreground">
        No organizations
      </div>
    );
  }

  if (isAdmin && !isLoading && !loadError && organizations.length === 0) {
    return (
      <div className="rounded-md border border-dashed border-border/80 px-3 py-1.5 text-xs text-muted-foreground">
        No organizations
      </div>
    );
  }

  return (
    <DropdownMenu
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) {
          setSearch("");
        }
      }}
    >
      <DropdownMenuTrigger asChild>
        <Button variant="outline" className="h-9 max-w-[min(100vw-8rem,280px)] justify-between gap-2 px-3 font-normal">
          <span className="flex min-w-0 items-center gap-2">
            <Building2 className="h-4 w-4 shrink-0 opacity-70" />
            <span className="truncate">
              {isLoading
                ? "Loading organizations…"
                : (current?.name ?? (isAdmin ? "All organizations" : "Select organization"))}
            </span>
          </span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="start"
        collisionPadding={12}
        className="flex w-[min(100vw-2rem,320px)] max-h-[min(70vh,420px)] flex-col overflow-hidden p-0"
        onCloseAutoFocus={(e) => e.preventDefault()}
      >
        <div className="shrink-0 border-b border-border/60 p-2">
          <DropdownMenuLabel className="px-1 py-0 text-xs text-muted-foreground">
            Organization
            {organizations.length > 0 ? ` · ${organizations.length} total` : ""}
          </DropdownMenuLabel>
          <div className="relative mt-2">
            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.stopPropagation()}
              placeholder="Search name or slug…"
              className="h-9 pl-8"
              autoFocus
            />
          </div>
        </div>

        {isLoading ? (
          <div className="flex items-center justify-center gap-2 px-3 py-6 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Loading organizations…
          </div>
        ) : loadError ? (
          <div className="space-y-2 px-3 py-4">
            <p className="text-sm text-destructive">{getApiErrorMessage(loadError)}</p>
            <Button type="button" variant="outline" size="sm" onClick={() => void organizationsQuery.refetch()}>
              Retry
            </Button>
          </div>
        ) : (
          <div
            className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1"
            onWheel={(event) => event.stopPropagation()}
            onTouchMove={(event) => event.stopPropagation()}
          >
            {isAdmin ? (
              <DropdownMenuItem
                onClick={() => {
                  setOrganizationId(null);
                  void queryClient.invalidateQueries();
                  setOpen(false);
                }}
                className="flex cursor-pointer items-center justify-between gap-2"
              >
                <span className="truncate">All organizations</span>
                {organizationId === null ? <Check className="h-4 w-4 shrink-0 text-primary" /> : null}
              </DropdownMenuItem>
            ) : null}
            {isAdmin && filtered.length > 0 ? <DropdownMenuSeparator className="my-1" /> : null}
            {filtered.length === 0 ? (
              <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                {search.trim() ? "No organizations match your search." : "No organizations found."}
              </p>
            ) : (
              filtered.map((org) => (
                <DropdownMenuItem
                  key={org.id}
                  onClick={() => {
                    setOrganizationId(org.id);
                    void queryClient.invalidateQueries();
                    setOpen(false);
                  }}
                  className="flex cursor-pointer items-center justify-between gap-2"
                >
                  <span className="truncate">{org.name}</span>
                  {org.id === organizationId ? <Check className="h-4 w-4 shrink-0 text-primary" /> : null}
                </DropdownMenuItem>
              ))
            )}
          </div>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
