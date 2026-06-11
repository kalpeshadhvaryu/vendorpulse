"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSearchParams } from "next/navigation";
import { MoreVertical, PlusCircle, Search } from "lucide-react";
import { toast } from "sonner";

import { AdminAccessGate } from "@/components/admin/admin-access-gate";
import { AdminNav } from "@/components/admin/admin-nav";
import { UserAccessFields } from "@/components/admin/user-access-fields";
import { confirmAction, generateTemporaryPassword } from "@/lib/admin/helpers";
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
import { getApiErrorMessage } from "@/lib/api/errors";
import {
  createOrganizationUser,
  deactivateOrganizationUser,
  fetchOrganizationUsers,
  fetchOrganizations,
  restoreOrganizationUser,
  updateUserGlobalAccess,
} from "@/lib/api/organizations";
import { queryKeys } from "@/lib/api/query-keys";
import {
  globalAdminFromAccountType,
  type AccountType,
  type OrganizationRole,
} from "@/lib/admin/user-access";
import { cn } from "@/lib/utils";

export default function AdminUsersPage() {
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();

  const [search, setSearch] = useState("");
  const [showDeactivated, setShowDeactivated] = useState(true);
  const [isCreateOpen, setIsCreateOpen] = useState(false);

  const [newUserOrgId, setNewUserOrgId] = useState("");
  const [newUserName, setNewUserName] = useState("");
  const [newUserEmail, setNewUserEmail] = useState("");
  const [newUserPassword, setNewUserPassword] = useState("");
  const [newUserAccountType, setNewUserAccountType] = useState<AccountType>("standard");
  const [newUserRole, setNewUserRole] = useState<OrganizationRole>("member");

  useEffect(() => {
    if (searchParams?.get("create") === "1") {
      setIsCreateOpen(true);
    }
  }, [searchParams]);

  const { data: users = [], isLoading } = useQuery({
    queryKey: queryKeys.organizationUsers({ include_trashed: true }),
    queryFn: async () => (await fetchOrganizationUsers({ include_trashed: true })).data,
  });

  const { data: organizations = [] } = useQuery({
    queryKey: queryKeys.organizations(),
    queryFn: async () => (await fetchOrganizations()).data,
  });

  const activeOrganizations = useMemo(
    () => organizations.filter((o) => !o.deleted_at),
    [organizations],
  );

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return users.filter((user) => {
      if (!showDeactivated && user.deleted_at) return false;
      if (!q) return true;
      return (
        user.name.toLowerCase().includes(q) ||
        user.email.toLowerCase().includes(q)
      );
    });
  }, [users, search, showDeactivated]);

  const createMutation = useMutation({
    mutationFn: createOrganizationUser,
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      setNewUserName("");
      setNewUserEmail("");
      setNewUserPassword("");
      setNewUserAccountType("standard");
      setNewUserRole("member");
      setIsCreateOpen(false);
      toast.success("User created");
      if (response.data?.id) {
        window.location.href = `/admin/users/${response.data.id}`;
      }
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const globalAccessMutation = useMutation({
    mutationFn: updateUserGlobalAccess,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      toast.success("Global access updated");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const deactivateMutation = useMutation({
    mutationFn: deactivateOrganizationUser,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      toast.success("User deactivated");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const restoreMutation = useMutation({
    mutationFn: restoreOrganizationUser,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      toast.success("User restored");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const handleCreate = () => {
    const orgId = newUserOrgId || activeOrganizations[0]?.id;
    if (!orgId) {
      toast.error("Select an organization");
      return;
    }
    if (!newUserName.trim() || !newUserEmail.trim() || !newUserPassword) {
      toast.error("Name, email, and password are required");
      return;
    }
    createMutation.mutate({
      organizationId: orgId,
      name: newUserName.trim(),
      email: newUserEmail.trim(),
      password: newUserPassword,
      role: newUserRole,
      is_admin: globalAdminFromAccountType(newUserAccountType),
    });
  };

  return (
    <AdminAccessGate>
      <div className="mx-auto flex max-w-6xl flex-col gap-8">
        <div className="space-y-4">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Users</h1>
            <p className="text-sm text-muted-foreground">
              User accounts, memberships, and platform-wide access.
            </p>
          </div>
          <AdminNav />
        </div>

        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search users..."
              className="pl-8"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            <input
              type="checkbox"
              checked={showDeactivated}
              onChange={(e) => setShowDeactivated(e.target.checked)}
              className="h-4 w-4"
            />
            Show deactivated
          </label>
          <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
            <DialogTrigger asChild>
              <Button className="gap-2">
                <PlusCircle className="h-4 w-4" />
                New user
              </Button>
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Create user</DialogTitle>
                <DialogDescription>Create an account and assign it to an organization.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 py-2">
                <UserAccessFields
                  accountType={newUserAccountType}
                  onAccountTypeChange={setNewUserAccountType}
                  organizationId={newUserOrgId}
                  onOrganizationIdChange={setNewUserOrgId}
                  organizations={activeOrganizations}
                  organizationRole={newUserRole}
                  onOrganizationRoleChange={setNewUserRole}
                />
                <div className="grid gap-2">
                  <Label>Name</Label>
                  <Input value={newUserName} onChange={(e) => setNewUserName(e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label>Email</Label>
                  <Input type="email" value={newUserEmail} onChange={(e) => setNewUserEmail(e.target.value)} />
                </div>
                <div className="grid gap-2">
                  <Label>Password</Label>
                  <div className="flex gap-2">
                    <Input
                      type="password"
                      value={newUserPassword}
                      onChange={(e) => setNewUserPassword(e.target.value)}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => setNewUserPassword(generateTemporaryPassword())}
                    >
                      Generate
                    </Button>
                  </div>
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
                  <TableHead>User</TableHead>
                  <TableHead>Memberships</TableHead>
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
                      No users found.
                    </TableCell>
                  </TableRow>
                ) : (
                  filtered.map((user) => {
                    const isDeactivated = Boolean(user.deleted_at);
                    return (
                      <TableRow key={user.id}>
                        <TableCell>
                          <Link href={`/admin/users/${user.id}`} className="font-medium hover:underline">
                            {user.name}
                          </Link>
                          <p className="text-xs text-muted-foreground">{user.email}</p>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-wrap gap-1">
                            {user.is_admin ? (
                              <Badge className="text-[10px] bg-indigo-500/10 text-indigo-600">Global</Badge>
                            ) : null}
                            {(user.organizations ?? []).map((org) => (
                              <Badge key={org.id} variant="secondary" className="text-[10px]">
                                {org.name}
                              </Badge>
                            ))}
                            {(user.organizations ?? []).length === 0 ? (
                              <span className="text-xs text-muted-foreground">None</span>
                            ) : null}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={isDeactivated ? "outline" : "default"} className={cn(!isDeactivated && "bg-emerald-500/10 text-emerald-600")}>
                            {isDeactivated ? "Deactivated" : "Active"}
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
                                <Link href={`/admin/users/${user.id}`}>Open</Link>
                              </DropdownMenuItem>
                              {!isDeactivated ? (
                                <>
                                  <DropdownMenuItem
                                    onClick={() =>
                                      globalAccessMutation.mutate({
                                        userId: user.id,
                                        is_admin: !Boolean(user.is_admin),
                                      })
                                    }
                                  >
                                    {user.is_admin ? "Revoke global access" : "Grant global access"}
                                  </DropdownMenuItem>
                                  <DropdownMenuSeparator />
                                  <DropdownMenuItem
                                    className="text-destructive"
                                    onClick={() => {
                                      if (confirmAction(`Deactivate ${user.name}?`)) {
                                        deactivateMutation.mutate(user.id);
                                      }
                                    }}
                                  >
                                    Deactivate
                                  </DropdownMenuItem>
                                </>
                              ) : (
                                <DropdownMenuItem onClick={() => restoreMutation.mutate(user.id)}>
                                  Restore user
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
