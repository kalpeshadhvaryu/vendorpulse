"use client";

import Link from "next/link";
import { useMemo, useState, useEffect } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { Building2, MoreVertical, PlusCircle, Search } from "lucide-react";
import { toast } from "sonner";

import { AdminAccessGate } from "@/components/admin/admin-access-gate";
import { AdminNav } from "@/components/admin/admin-nav";
import { adminSelectClass, confirmAction, normalizeSlug } from "@/lib/admin/helpers";
import { appPath } from "@/lib/app-path";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { fetchMe } from "@/lib/api/auth";
import { getApiErrorMessage } from "@/lib/api/errors";
import {
  createOrganization,
  fetchOrganizations,
  permanentlyDeleteOrganization,
  restoreOrganization,
  softDeleteOrganization,
} from "@/lib/api/organizations";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

export default function AdminOrganizationsPage() {
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const setUser = useAuthStore((s) => s.setUser);

  const [search, setSearch] = useState("");
  const [showTrashed, setShowTrashed] = useState(true);
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [createName, setCreateName] = useState("");
  const [createSlug, setCreateSlug] = useState("");
  const [createOwnerEmail, setCreateOwnerEmail] = useState("");
  const [createPhoneCountryCode, setCreatePhoneCountryCode] = useState("");
  const [createPhoneNumber, setCreatePhoneNumber] = useState("");

  useEffect(() => {
    if (searchParams?.get("create") === "1") {
      setIsCreateOpen(true);
    }
  }, [searchParams]);

  const { data: organizations = [], isLoading } = useQuery({
    queryKey: queryKeys.organizations({ include_trashed: true }),
    queryFn: async () => (await fetchOrganizations({ include_trashed: true })).data,
  });

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return organizations.filter((org) => {
      if (!showTrashed && org.deleted_at) return false;
      if (!q) return true;
      return (
        org.name.toLowerCase().includes(q) ||
        (org.slug ?? "").toLowerCase().includes(q)
      );
    });
  }, [organizations, search, showTrashed]);

  const createMutation = useMutation({
    mutationFn: createOrganization,
    onSuccess: async (response) => {
      const refreshed = await fetchMe();
      setUser(refreshed);
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      await queryClient.invalidateQueries({ queryKey: queryKeys.me });
      setCreateName("");
      setCreateSlug("");
      setCreateOwnerEmail("");
      setCreatePhoneCountryCode("");
      setCreatePhoneNumber("");
      setIsCreateOpen(false);
      toast.success("Organization created");
      if (response.data?.id) {
        window.location.href = appPath(`/admin/organizations/${response.data.id}`);
      }
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const softDeleteMutation = useMutation({
    mutationFn: softDeleteOrganization,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      toast.success("Organization moved to trash");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const restoreMutation = useMutation({
    mutationFn: restoreOrganization,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      toast.success("Organization restored");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const forceDeleteMutation = useMutation({
    mutationFn: permanentlyDeleteOrganization,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      toast.success("Organization permanently deleted");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const handleCreate = () => {
    if (!createName.trim()) {
      toast.error("Organization name is required");
      return;
    }
    createMutation.mutate({
      name: createName.trim(),
      slug: createSlug.trim() || undefined,
      owner_user_email: createOwnerEmail.trim() || undefined,
      phone_country_code: createPhoneCountryCode.trim() || undefined,
      phone_number: createPhoneNumber.trim() || undefined,
    });
  };

  return (
    <AdminAccessGate>
      <div className="mx-auto flex max-w-6xl flex-col gap-8">
        <div className="space-y-4">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Organizations</h1>
            <p className="text-sm text-muted-foreground">
              Tenant records, membership, and lifecycle controls.
            </p>
          </div>
          <AdminNav />
        </div>

        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search organizations..."
              className="pl-8"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            <input
              type="checkbox"
              checked={showTrashed}
              onChange={(e) => setShowTrashed(e.target.checked)}
              className="h-4 w-4"
            />
            Show trashed
          </label>
          <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
            <DialogTrigger asChild>
              <Button className="gap-2">
                <PlusCircle className="h-4 w-4" />
                New organization
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Create organization</DialogTitle>
                <DialogDescription>Add a tenant and optionally assign an initial owner by email.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 py-2">
                <div className="grid gap-2">
                  <Label htmlFor="org-name">Name</Label>
                  <Input id="org-name" value={createName} onChange={(e) => setCreateName(e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="org-slug">Slug</Label>
                  <Input
                    id="org-slug"
                    value={createSlug}
                    onChange={(e) => setCreateSlug(e.target.value)}
                    onBlur={(e) => setCreateSlug(normalizeSlug(e.target.value))}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="owner-email">Owner email (optional)</Label>
                  <Input
                    id="owner-email"
                    type="email"
                    value={createOwnerEmail}
                    onChange={(e) => setCreateOwnerEmail(e.target.value)}
                  />
                </div>
              </div>
              <DialogFooter>
                <Button onClick={handleCreate} disabled={createMutation.isPending}>
                  {createMutation.isPending ? "Creating..." : "Create"}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>

        <Card className="border-border/60">
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Organization</TableHead>
                  <TableHead>Slug</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  <TableRow>
                    <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                      Loading...
                    </TableCell>
                  </TableRow>
                ) : filtered.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                      No organizations found.
                    </TableCell>
                  </TableRow>
                ) : (
                  filtered.map((org) => {
                    const isDeleted = Boolean(org.deleted_at);
                    return (
                      <TableRow key={org.id}>
                        <TableCell>
                          <Link
                            href={`/admin/organizations/${org.id}`}
                            className="font-medium hover:underline"
                          >
                            {org.name}
                          </Link>
                        </TableCell>
                        <TableCell className="text-muted-foreground">{org.slug}</TableCell>
                        <TableCell>
                          <Badge
                            variant={isDeleted ? "outline" : "default"}
                            className={cn(
                              !isDeleted && "bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20",
                            )}
                          >
                            {isDeleted ? "Trashed" : "Active"}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-right">
                          <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                              <Button variant="ghost" size="icon" className="h-8 w-8">
                                <MoreVertical className="h-4 w-4" />
                              </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                              <DropdownMenuLabel>Actions</DropdownMenuLabel>
                              <DropdownMenuItem asChild>
                                <Link href={`/admin/organizations/${org.id}`}>
                                  <Building2 className="mr-2 h-4 w-4" />
                                  Open
                                </Link>
                              </DropdownMenuItem>
                              {isDeleted ? (
                                <>
                                  <DropdownMenuItem
                                    onClick={() => restoreMutation.mutate(org.id)}
                                  >
                                    Restore
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    className="text-destructive"
                                    onClick={() => {
                                      if (
                                        confirmAction(
                                          `Permanently delete "${org.name}"? This cannot be undone.`,
                                        )
                                      ) {
                                        forceDeleteMutation.mutate(org.id);
                                      }
                                    }}
                                  >
                                    Delete permanently
                                  </DropdownMenuItem>
                                </>
                              ) : (
                                <DropdownMenuItem
                                  className="text-destructive"
                                  onClick={() => {
                                    if (
                                      confirmAction(`Soft delete "${org.name}"?`)
                                    ) {
                                      softDeleteMutation.mutate(org.id);
                                    }
                                  }}
                                >
                                  Move to trash
                                </DropdownMenuItem>
                              )}
                            </DropdownMenuContent>
                          </DropdownMenu>
                        </TableCell>
                      </TableRow>
                    );
                  })
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AdminAccessGate>
  );
}
