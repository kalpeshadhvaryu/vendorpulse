"use client";

import Link from "next/link";
import { use, useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, MoreVertical, PlusCircle, Search, UserPlus } from "lucide-react";
import { toast } from "sonner";

import { AdminAccessGate } from "@/components/admin/admin-access-gate";
import { AdminNav } from "@/components/admin/admin-nav";
import {
  adminSelectClass,
  confirmAction,
  copyToClipboard,
  generateTemporaryPassword,
  normalizeSlug,
} from "@/lib/admin/helpers";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
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
import type { User } from "@/lib/api/types";
import {
  assignOrganizationMember,
  createOrganizationUser,
  detachOrganizationMember,
  fetchOrganization,
  permanentlyDeleteOrganization,
  restoreOrganization,
  softDeleteOrganization,
  updateOrganization,
  updateOrganizationMember,
} from "@/lib/api/organizations";
import { queryKeys } from "@/lib/api/query-keys";
import { cn } from "@/lib/utils";

type PageProps = {
  params: Promise<{ organizationId: string }>;
};

export default function AdminOrganizationDetailPage({ params }: PageProps) {
  const { organizationId } = use(params);
  const queryClient = useQueryClient();

  const [activeTab, setActiveTab] = useState("profile");
  const [memberSearch, setMemberSearch] = useState("");

  const [editName, setEditName] = useState("");
  const [editSlug, setEditSlug] = useState("");
  const [editPhoneCountryCode, setEditPhoneCountryCode] = useState("");
  const [editPhoneNumber, setEditPhoneNumber] = useState("");

  const [isAssignOpen, setIsAssignOpen] = useState(false);
  const [assignEmail, setAssignEmail] = useState("");
  const [assignRole, setAssignRole] = useState<"member" | "owner" | "admin">("member");

  const [isCreateUserOpen, setIsCreateUserOpen] = useState(false);
  const [newUserName, setNewUserName] = useState("");
  const [newUserEmail, setNewUserEmail] = useState("");
  const [newUserPassword, setNewUserPassword] = useState("");
  const [newUserRole, setNewUserRole] = useState<"member" | "owner" | "admin">("member");

  const detailQuery = useQuery({
    queryKey: queryKeys.organization(organizationId),
    queryFn: async () => (await fetchOrganization(organizationId)).data,
    retry: false,
  });

  const organization = detailQuery.data?.organization;
  const members = detailQuery.data?.members ?? [];
  const isDeleted = Boolean(organization?.deleted_at);

  useEffect(() => {
    if (!organization) return;
    setEditName(organization.name);
    setEditSlug(organization.slug ?? "");
    setEditPhoneCountryCode(organization.phone_country_code ?? "");
    setEditPhoneNumber(organization.phone_number ?? "");
  }, [organization?.id, organization?.name, organization?.slug, organization?.phone_country_code, organization?.phone_number]);

  const filteredMembers = useMemo(() => {
    const q = memberSearch.trim().toLowerCase();
    if (!q) return members;
    return members.filter(
      (m) =>
        m.name.toLowerCase().includes(q) ||
        m.email.toLowerCase().includes(q),
    );
  }, [members, memberSearch]);

  const updateOrgMutation = useMutation({
    mutationFn: updateOrganization,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organization(organizationId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      toast.success("Organization updated");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const assignMutation = useMutation({
    mutationFn: assignOrganizationMember,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organization(organizationId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      setAssignEmail("");
      setIsAssignOpen(false);
      toast.success("User assigned");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const createUserMutation = useMutation({
    mutationFn: createOrganizationUser,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organization(organizationId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      setNewUserName("");
      setNewUserEmail("");
      setNewUserPassword("");
      setIsCreateUserOpen(false);
      toast.success("User created");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const updateMemberMutation = useMutation({
    mutationFn: updateOrganizationMember,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organization(organizationId) });
      toast.success("Member updated");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const detachMutation = useMutation({
    mutationFn: ({ userId }: { userId: string }) =>
      detachOrganizationMember(organizationId, userId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organization(organizationId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      toast.success("Member removed");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const softDeleteMutation = useMutation({
    mutationFn: () => softDeleteOrganization(organizationId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organization(organizationId) });
      toast.success("Organization moved to trash");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const restoreMutation = useMutation({
    mutationFn: () => restoreOrganization(organizationId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organization(organizationId) });
      toast.success("Organization restored");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const forceDeleteMutation = useMutation({
    mutationFn: () => permanentlyDeleteOrganization(organizationId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationsAll });
      toast.success("Organization permanently deleted");
      window.location.href = "/admin/organizations";
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const handleSaveProfile = () => {
    if (!editName.trim() || !editSlug.trim()) {
      toast.error("Name and slug are required");
      return;
    }
    updateOrgMutation.mutate({
      organizationId,
      name: editName.trim(),
      slug: normalizeSlug(editSlug),
      phone_country_code: editPhoneCountryCode.trim() || undefined,
      phone_number: editPhoneNumber.trim() || undefined,
    });
  };

  if (detailQuery.isLoading) {
    return (
      <AdminAccessGate>
        <p className="text-sm text-muted-foreground">Loading organization...</p>
      </AdminAccessGate>
    );
  }

  if (detailQuery.isError || !organization) {
    return (
      <AdminAccessGate>
        <p className="text-sm text-muted-foreground">
          {detailQuery.isError
            ? getApiErrorMessage(detailQuery.error)
            : "Organization not found."}
        </p>
        <p className="mt-2 font-mono text-xs text-muted-foreground">{organizationId}</p>
        <Button variant="link" asChild className="mt-2 px-0">
          <Link href="/admin/organizations">Back to organizations</Link>
        </Button>
      </AdminAccessGate>
    );
  }

  const profileDirty =
    editName !== organization.name ||
    editSlug !== (organization.slug ?? "") ||
    editPhoneCountryCode !== (organization.phone_country_code ?? "") ||
    editPhoneNumber !== (organization.phone_number ?? "");

  return (
    <AdminAccessGate>
      <div className="mx-auto flex max-w-6xl flex-col gap-8">
        <div className="space-y-4">
          <Button variant="ghost" size="sm" className="-ml-2 w-fit gap-1 px-2" asChild>
            <Link href="/admin/organizations">
              <ArrowLeft className="h-4 w-4" />
              Organizations
            </Link>
          </Button>
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h1 className="text-3xl font-bold tracking-tight">{organization.name}</h1>
              <p className="text-sm text-muted-foreground">
                <span className="font-mono">{organization.slug}</span>
                {isDeleted ? (
                  <Badge variant="outline" className="ml-2">
                    Trashed
                  </Badge>
                ) : (
                  <Badge className="ml-2 bg-emerald-500/10 text-emerald-600">Active</Badge>
                )}
              </p>
            </div>
            <div className="text-sm text-muted-foreground">{members.length} members</div>
          </div>
          <AdminNav />
        </div>

        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="profile">Profile</TabsTrigger>
            <TabsTrigger value="members">Members</TabsTrigger>
            <TabsTrigger value="danger">Danger zone</TabsTrigger>
          </TabsList>

          <TabsContent value="profile" className="space-y-4 pt-4">
            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Organization profile</CardTitle>
                <CardDescription>Update tenant identity and contact details.</CardDescription>
              </CardHeader>
              <CardContent className="grid max-w-lg gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="edit-name">Name</Label>
                  <Input
                    id="edit-name"
                    value={editName || organization.name}
                    onChange={(e) => setEditName(e.target.value)}
                    disabled={isDeleted}
                  />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="edit-slug">Slug</Label>
                  <Input
                    id="edit-slug"
                    value={editSlug || organization.slug || ""}
                    onChange={(e) => setEditSlug(e.target.value)}
                    onBlur={(e) => setEditSlug(normalizeSlug(e.target.value))}
                    disabled={isDeleted}
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="grid gap-2">
                    <Label>Country code</Label>
                    <Input
                      value={editPhoneCountryCode}
                      onChange={(e) => setEditPhoneCountryCode(e.target.value)}
                      placeholder="+1"
                      disabled={isDeleted}
                    />
                  </div>
                  <div className="grid gap-2">
                    <Label>Phone</Label>
                    <Input
                      value={editPhoneNumber}
                      onChange={(e) => setEditPhoneNumber(e.target.value)}
                      disabled={isDeleted}
                    />
                  </div>
                </div>
                <Button
                  onClick={handleSaveProfile}
                  disabled={isDeleted || updateOrgMutation.isPending || !profileDirty}
                >
                  {updateOrgMutation.isPending ? "Saving..." : "Save changes"}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="members" className="space-y-4 pt-4">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div className="relative max-w-sm flex-1">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search members..."
                  className="pl-8"
                  value={memberSearch}
                  onChange={(e) => setMemberSearch(e.target.value)}
                />
              </div>
              <div className="flex gap-2">
                <Button variant="outline" className="gap-2" onClick={() => setIsAssignOpen(true)} disabled={isDeleted}>
                  <UserPlus className="h-4 w-4" />
                  Assign existing
                </Button>
                <Button className="gap-2" onClick={() => setIsCreateUserOpen(true)} disabled={isDeleted}>
                  <PlusCircle className="h-4 w-4" />
                  Create user
                </Button>
              </div>
            </div>

            <Card className="border-border/60">
              <CardContent className="p-0">
                <MemberTable
                  members={filteredMembers}
                  isDeleted={isDeleted}
                  onRoleChange={(userId, role) =>
                    updateMemberMutation.mutate({ organizationId, userId, role })
                  }
                  onRemove={(userId, name) => {
                    if (confirmAction(`Remove ${name} from this organization?`)) {
                      detachMutation.mutate({ userId });
                    }
                  }}
                />
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="danger" className="space-y-4 pt-4">
            <Card className="border-destructive/40">
              <CardHeader>
                <CardTitle className="text-base text-destructive">Danger zone</CardTitle>
                <CardDescription>Destructive actions for this organization.</CardDescription>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-2">
                {isDeleted ? (
                  <>
                    <Button
                      variant="secondary"
                      onClick={() => restoreMutation.mutate()}
                      disabled={restoreMutation.isPending}
                    >
                      Restore organization
                    </Button>
                    <Button
                      variant="destructive"
                      onClick={() => {
                        if (
                          confirmAction(
                            "Permanently delete this organization? This cannot be undone.",
                          )
                        ) {
                          forceDeleteMutation.mutate();
                        }
                      }}
                      disabled={forceDeleteMutation.isPending}
                    >
                      Delete permanently
                    </Button>
                  </>
                ) : (
                  <Button
                    variant="destructive"
                    onClick={() => {
                      if (confirmAction("Move this organization to trash?")) {
                        softDeleteMutation.mutate();
                      }
                    }}
                    disabled={softDeleteMutation.isPending}
                  >
                    Move to trash
                  </Button>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>

      <Dialog open={isAssignOpen} onOpenChange={setIsAssignOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Assign existing user</DialogTitle>
            <DialogDescription>Attach a user by email to {organization.name}.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-2">
            <div className="grid gap-2">
              <Label>Email</Label>
              <Input value={assignEmail} onChange={(e) => setAssignEmail(e.target.value)} type="email" />
            </div>
            <div className="grid gap-2">
              <Label>Role</Label>
              <select
                className={adminSelectClass}
                value={assignRole}
                onChange={(e) => setAssignRole(e.target.value as typeof assignRole)}
              >
                <option value="member">Member</option>
                <option value="owner">Owner</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <DialogFooter>
            <Button
              onClick={() => {
                if (!assignEmail.trim()) {
                  toast.error("Email is required");
                  return;
                }
                assignMutation.mutate({
                  organizationId,
                  user_email: assignEmail.trim(),
                  role: assignRole,
                });
              }}
              disabled={assignMutation.isPending}
            >
              Assign
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={isCreateUserOpen} onOpenChange={setIsCreateUserOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create user</DialogTitle>
            <DialogDescription>Create an account and assign it to {organization.name}.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-2">
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
            <div className="grid gap-2">
              <Label>Role</Label>
              <select
                className={adminSelectClass}
                value={newUserRole}
                onChange={(e) => setNewUserRole(e.target.value as typeof newUserRole)}
              >
                <option value="member">Member</option>
                <option value="owner">Owner</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <DialogFooter>
            <Button
              onClick={() => {
                if (!newUserName.trim() || !newUserEmail.trim() || !newUserPassword) {
                  toast.error("Name, email, and password are required");
                  return;
                }
                createUserMutation.mutate({
                  organizationId,
                  name: newUserName.trim(),
                  email: newUserEmail.trim(),
                  password: newUserPassword,
                  role: newUserRole,
                });
              }}
              disabled={createUserMutation.isPending}
            >
              Create user
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AdminAccessGate>
  );
}

function MemberTable({
  members,
  isDeleted,
  onRoleChange,
  onRemove,
}: {
  members: User[];
  isDeleted: boolean;
  onRoleChange: (userId: string, role: "member" | "owner" | "admin") => void;
  onRemove: (userId: string, name: string) => void;
}) {
  if (members.length === 0) {
    return (
      <p className="px-4 py-8 text-center text-sm text-muted-foreground">No members yet.</p>
    );
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead>User</TableHead>
          <TableHead>Role</TableHead>
          <TableHead className="text-right">Actions</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {members.map((member) => {
          const role =
            (member.membership_role as "member" | "owner" | "admin" | undefined) ??
            (member.organizations?.find((o) => o.role)?.role as "member" | "owner" | "admin" | undefined) ??
            "member";

          return (
            <TableRow key={member.id}>
              <TableCell>
                <Link href={`/admin/users/${member.id}`} className="font-medium hover:underline">
                  {member.name}
                </Link>
                <p className="text-xs text-muted-foreground">{member.email}</p>
              </TableCell>
              <TableCell>
                <select
                  className={cn(adminSelectClass, "max-w-[140px]")}
                  value={role}
                  disabled={isDeleted}
                  onChange={(e) =>
                    onRoleChange(member.id, e.target.value as "member" | "owner" | "admin")
                  }
                >
                  <option value="member">Member</option>
                  <option value="owner">Owner</option>
                  <option value="admin">Admin</option>
                </select>
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
                      <Link href={`/admin/users/${member.id}`}>Open user</Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => void copyToClipboard(member.email, "Email")}>
                      Copy email
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      className="text-destructive"
                      disabled={isDeleted}
                      onClick={() => onRemove(member.id, member.name)}
                    >
                      Remove from org
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
  );
}
