"use client";

import Link from "next/link";
import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { Building2, PlusCircle, UserPlus, Users } from "lucide-react";

import { AdminAccessGate } from "@/components/admin/admin-access-gate";
import { AdminNav } from "@/components/admin/admin-nav";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchOrganizations, fetchOrganizationUsers } from "@/lib/api/organizations";
import { queryKeys } from "@/lib/api/query-keys";

export default function AdminOverviewPage() {
  const { data: organizations = [] } = useQuery({
    queryKey: queryKeys.organizations({ include_trashed: true }),
    queryFn: async () => (await fetchOrganizations({ include_trashed: true })).data,
  });

  const { data: users = [] } = useQuery({
    queryKey: queryKeys.organizationUsers({ include_trashed: true }),
    queryFn: async () => (await fetchOrganizationUsers({ include_trashed: true })).data,
  });

  const stats = useMemo(() => {
    const activeOrgs = organizations.filter((o) => !o.deleted_at);
    const trashedOrgs = organizations.filter((o) => o.deleted_at);
    const activeUsers = users.filter((u) => !u.deleted_at);
    const deactivatedUsers = users.filter((u) => u.deleted_at);
    const globalAdmins = activeUsers.filter((u) => u.is_admin);
    const usersWithoutOrg = activeUsers.filter((u) => (u.organizations ?? []).length === 0);

    return {
      activeOrgs: activeOrgs.length,
      trashedOrgs: trashedOrgs.length,
      activeUsers: activeUsers.length,
      deactivatedUsers: deactivatedUsers.length,
      globalAdmins: globalAdmins.length,
      usersWithoutOrg: usersWithoutOrg.length,
    };
  }, [organizations, users]);

  return (
    <AdminAccessGate>
      <div className="mx-auto flex max-w-6xl flex-col gap-8">
        <div className="space-y-4">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Administration</h1>
            <p className="text-sm text-muted-foreground">
              Manage organizations, memberships, and platform access.
            </p>
          </div>
          <AdminNav />
        </div>

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Active organizations</CardDescription>
              <CardTitle className="text-3xl">{stats.activeOrgs}</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              {stats.trashedOrgs} in trash
            </CardContent>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Active users</CardDescription>
              <CardTitle className="text-3xl">{stats.activeUsers}</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              {stats.deactivatedUsers} deactivated
            </CardContent>
          </Card>
          <Card className="border-border/60">
            <CardHeader className="pb-2">
              <CardDescription>Global admins</CardDescription>
              <CardTitle className="text-3xl">{stats.globalAdmins}</CardTitle>
            </CardHeader>
            <CardContent className="text-xs text-muted-foreground">
              {stats.usersWithoutOrg} users without memberships
            </CardContent>
          </Card>
        </div>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="text-base">Quick actions</CardTitle>
            <CardDescription>Jump into common admin workflows.</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            <Button variant="outline" className="gap-2" asChild>
              <Link href="/admin/organizations?create=1">
                <PlusCircle className="h-4 w-4" />
                New organization
              </Link>
            </Button>
            <Button variant="outline" className="gap-2" asChild>
              <Link href="/admin/users?create=1">
                <UserPlus className="h-4 w-4" />
                New user
              </Link>
            </Button>
            <Button variant="outline" className="gap-2" asChild>
              <Link href="/admin/organizations">
                <Building2 className="h-4 w-4" />
                Browse organizations
              </Link>
            </Button>
            <Button variant="outline" className="gap-2" asChild>
              <Link href="/admin/users">
                <Users className="h-4 w-4" />
                Browse users
              </Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </AdminAccessGate>
  );
}
