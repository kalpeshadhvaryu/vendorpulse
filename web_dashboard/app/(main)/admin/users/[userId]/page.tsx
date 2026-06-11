"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft } from "lucide-react";
import { toast } from "sonner";

import { AdminAccessGate } from "@/components/admin/admin-access-gate";
import { AdminNav } from "@/components/admin/admin-nav";
import { UserAccessFields } from "@/components/admin/user-access-fields";
import {
  adminSelectClass,
  confirmAction,
  generateTemporaryPassword,
} from "@/lib/admin/helpers";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { getApiErrorMessage } from "@/lib/api/errors";
import {
  deactivateOrganizationUser,
  fetchOrganizationUser,
  restoreOrganizationUser,
  updateOrganizationMember,
  updateOrganizationUser,
  updateUserGlobalAccess,
} from "@/lib/api/organizations";
import { queryKeys } from "@/lib/api/query-keys";
import {
  accountTypeFromGlobalAdmin,
  globalAdminFromAccountType,
  ORGANIZATION_ROLE_OPTIONS,
  type AccountType,
  type OrganizationRole,
} from "@/lib/admin/user-access";

type PageProps = {
  params: Promise<{ userId: string }>;
};

export default function AdminUserDetailPage({ params }: PageProps) {
  const { userId } = use(params);
  const queryClient = useQueryClient();

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [timezone, setTimezone] = useState("");
  const [password, setPassword] = useState("");
  const [defaultOrgId, setDefaultOrgId] = useState("");
  const [accountType, setAccountType] = useState<AccountType>("standard");
  const [accessOrgId, setAccessOrgId] = useState("");
  const [accessOrgRole, setAccessOrgRole] = useState<OrganizationRole>("member");

  const userQuery = useQuery({
    queryKey: queryKeys.organizationUser(userId),
    queryFn: async () => (await fetchOrganizationUser(userId)).data,
    retry: false,
  });

  const user = userQuery.data;
  const isDeactivated = Boolean(user?.deleted_at);
  const memberships = user?.organizations ?? [];

  useEffect(() => {
    if (!user) return;
    setName(user.name);
    setEmail(user.email);
    setTimezone(user.timezone ?? "");
    setDefaultOrgId(user.default_organization_id ?? "");
    setAccountType(accountTypeFromGlobalAdmin(Boolean(user.is_admin)));

    const orgs = user.organizations ?? [];
    const preferredOrgId =
      user.default_organization_id ?? orgs[0]?.id ?? "";
    setAccessOrgId(preferredOrgId);
    const preferredOrg = orgs.find((org) => org.id === preferredOrgId);
    setAccessOrgRole((preferredOrg?.role ?? "member") as OrganizationRole);
  }, [
    user?.id,
    user?.name,
    user?.email,
    user?.timezone,
    user?.default_organization_id,
    user?.is_admin,
    user?.organizations,
  ]);

  const handleAccessOrgChange = (organizationId: string) => {
    setAccessOrgId(organizationId);
    const org = memberships.find((membership) => membership.id === organizationId);
    setAccessOrgRole((org?.role ?? "member") as OrganizationRole);
  };

  const [isSavingProfile, setIsSavingProfile] = useState(false);

  const deactivateMutation = useMutation({
    mutationFn: () => deactivateOrganizationUser(userId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUser(userId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      toast.success("User deactivated");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const restoreMutation = useMutation({
    mutationFn: () => restoreOrganizationUser(userId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUser(userId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      toast.success("User restored");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const roleMutation = useMutation({
    mutationFn: updateOrganizationMember,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUser(userId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      toast.success("Organization role updated");
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const handleSave = async () => {
    if (!name.trim() || !email.trim()) {
      toast.error("Name and email are required");
      return;
    }

    setIsSavingProfile(true);
    try {
      await updateOrganizationUser({
        userId,
        name: name.trim(),
        email: email.trim(),
        timezone: timezone.trim() || null,
        default_organization_id: defaultOrgId || null,
        ...(password ? { password } : {}),
      });

      const nextIsGlobalAdmin = globalAdminFromAccountType(accountType);
      if (Boolean(user?.is_admin) !== nextIsGlobalAdmin) {
        await updateUserGlobalAccess({ userId, is_admin: nextIsGlobalAdmin });
      }

      if (accessOrgId) {
        const currentOrg = memberships.find((org) => org.id === accessOrgId);
        const currentRole = (currentOrg?.role ?? "member") as OrganizationRole;
        if (currentRole !== accessOrgRole) {
          await updateOrganizationMember({
            organizationId: accessOrgId,
            userId,
            role: accessOrgRole,
          });
        }
      }

      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUser(userId) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationUsers() });
      setPassword("");
      toast.success("User updated");
    } catch (error) {
      toast.error(getApiErrorMessage(error));
    } finally {
      setIsSavingProfile(false);
    }
  };

  if (userQuery.isLoading) {
    return (
      <AdminAccessGate>
        <p className="text-sm text-muted-foreground">Loading user...</p>
      </AdminAccessGate>
    );
  }

  if (userQuery.isError || !user) {
    return (
      <AdminAccessGate>
        <p className="text-sm text-muted-foreground">
          {userQuery.isError ? getApiErrorMessage(userQuery.error) : "User not found."}
        </p>
        <p className="mt-2 font-mono text-xs text-muted-foreground">{userId}</p>
        <Button variant="link" asChild className="mt-2 px-0">
          <Link href="/admin/users">Back to users</Link>
        </Button>
      </AdminAccessGate>
    );
  }

  const accessOrg = memberships.find((org) => org.id === accessOrgId);
  const savedAccessOrgRole = (accessOrg?.role ?? "member") as OrganizationRole;

  const profileDirty =
    name !== user.name ||
    email !== user.email ||
    timezone !== (user.timezone ?? "") ||
    defaultOrgId !== (user.default_organization_id ?? "") ||
    accountType !== accountTypeFromGlobalAdmin(Boolean(user.is_admin)) ||
    (accessOrgId !== "" && accessOrgRole !== savedAccessOrgRole) ||
    password.length > 0;

  return (
    <AdminAccessGate>
      <div className="mx-auto flex max-w-6xl flex-col gap-8">
        <div className="space-y-4">
          <Button variant="ghost" size="sm" className="-ml-2 w-fit gap-1 px-2" asChild>
            <Link href="/admin/users">
              <ArrowLeft className="h-4 w-4" />
              Users
            </Link>
          </Button>
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h1 className="text-3xl font-bold tracking-tight">{user.name}</h1>
              <p className="text-sm text-muted-foreground">{user.email}</p>
            </div>
            <div className="flex flex-wrap gap-2">
              {user.is_admin ? (
                <Badge className="bg-indigo-500/10 text-indigo-600">Global admin</Badge>
              ) : null}
              <Badge variant={isDeactivated ? "outline" : "default"} className={!isDeactivated ? "bg-emerald-500/10 text-emerald-600" : ""}>
                {isDeactivated ? "Deactivated" : "Active"}
              </Badge>
            </div>
          </div>
          <AdminNav />
        </div>

        <Tabs defaultValue="profile">
          <TabsList>
            <TabsTrigger value="profile">Profile</TabsTrigger>
            <TabsTrigger value="memberships">Memberships</TabsTrigger>
            <TabsTrigger value="access">Access</TabsTrigger>
          </TabsList>

          <TabsContent value="profile" className="space-y-4 pt-4">
            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Account</CardTitle>
                <CardDescription>Same access fields as user creation, plus identity and password.</CardDescription>
              </CardHeader>
              <CardContent className="grid max-w-lg gap-4">
                <UserAccessFields
                  accountType={accountType}
                  onAccountTypeChange={setAccountType}
                  organizationId={accessOrgId}
                  onOrganizationIdChange={handleAccessOrgChange}
                  organizations={memberships}
                  organizationRole={accessOrgRole}
                  onOrganizationRoleChange={setAccessOrgRole}
                  disabled={isDeactivated}
                  organizationRequired={memberships.length > 0}
                />
                <div className="grid gap-2">
                  <Label>Name</Label>
                  <Input value={name} onChange={(e) => setName(e.target.value)} disabled={isDeactivated} />
                </div>
                <div className="grid gap-2">
                  <Label>Email</Label>
                  <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} disabled={isDeactivated} />
                </div>
                <div className="grid gap-2">
                  <Label>Timezone</Label>
                  <Input value={timezone} onChange={(e) => setTimezone(e.target.value)} placeholder="UTC" disabled={isDeactivated} />
                </div>
                <div className="grid gap-2">
                  <Label>Default organization</Label>
                  <select
                    className={adminSelectClass}
                    value={defaultOrgId}
                    onChange={(e) => setDefaultOrgId(e.target.value)}
                    disabled={isDeactivated}
                  >
                    <option value="">None</option>
                    {memberships.map((org) => (
                      <option key={org.id} value={org.id}>
                        {org.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="grid gap-2">
                  <Label>New password (optional)</Label>
                  <div className="flex gap-2">
                    <Input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      disabled={isDeactivated}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      disabled={isDeactivated}
                      onClick={() => setPassword(generateTemporaryPassword())}
                    >
                      Generate
                    </Button>
                  </div>
                </div>
                <Button
                  onClick={() => void handleSave()}
                  disabled={isDeactivated || !profileDirty || isSavingProfile}
                >
                  {isSavingProfile ? "Saving..." : "Save changes"}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="memberships" className="space-y-4 pt-4">
            <Card className="border-border/60">
              <CardHeader>
                <CardTitle className="text-base">Organization memberships</CardTitle>
                <CardDescription>
                  All organization memberships. Use the same Role labels as on the profile when editing multiple orgs.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-3">
                {memberships.length === 0 ? (
                  <p className="text-sm text-muted-foreground">No organization memberships.</p>
                ) : (
                  memberships.map((org) => {
                    const orgRole = (org.role ?? "member") as "member" | "owner" | "admin";
                    return (
                      <div
                        key={org.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border/60 px-3 py-2"
                      >
                        <div className="min-w-0 flex-1">
                          <p className="font-medium">{org.name}</p>
                          {user.default_organization_id === org.id ? (
                            <p className="text-xs text-muted-foreground">Default organization</p>
                          ) : null}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                          <select
                            className={adminSelectClass}
                            value={orgRole}
                            disabled={isDeactivated || roleMutation.isPending}
                            aria-label={`Role for ${org.name}`}
                            onChange={(e) => {
                              const role = e.target.value as OrganizationRole;
                              roleMutation.mutate({
                                organizationId: org.id,
                                userId,
                                role,
                              });
                              if (org.id === accessOrgId) {
                                setAccessOrgRole(role);
                              }
                            }}
                          >
                            {ORGANIZATION_ROLE_OPTIONS.map((option) => (
                              <option key={option.value} value={option.value}>
                                {option.label}
                              </option>
                            ))}
                          </select>
                          <Button variant="outline" size="sm" asChild>
                            <Link href={`/admin/organizations/${org.id}`}>Open org</Link>
                          </Button>
                        </div>
                      </div>
                    );
                  })
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="access" className="space-y-4 pt-4">
            <Card className="border-destructive/40">
              <CardHeader>
                <CardTitle className="text-base text-destructive">Account status</CardTitle>
              </CardHeader>
              <CardContent>
                {isDeactivated ? (
                  <Button onClick={() => restoreMutation.mutate()} disabled={restoreMutation.isPending}>
                    Restore user
                  </Button>
                ) : (
                  <Button
                    variant="destructive"
                    onClick={() => {
                      if (confirmAction(`Deactivate ${user.name}? They will not be able to sign in.`)) {
                        deactivateMutation.mutate();
                      }
                    }}
                    disabled={deactivateMutation.isPending}
                  >
                    Deactivate user
                  </Button>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AdminAccessGate>
  );
}
