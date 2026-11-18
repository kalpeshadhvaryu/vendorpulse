"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Globe, RefreshCw } from "lucide-react";
import { toast } from "sonner";
import { AdminAccessGate } from "@/components/admin/admin-access-gate";
import { AdminNav } from "@/components/admin/admin-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  assignCloudflareZone,
  fetchAdminCloudflareZones,
  syncCloudflareZones,
} from "@/lib/api/cloudflare";
import { getApiErrorMessage } from "@/lib/api/errors";
import { fetchOrganizations } from "@/lib/api/organizations";
import { queryKeys } from "@/lib/api/query-keys";

export default function AdminCloudflareDomainsPage() {
  const queryClient = useQueryClient();
  const [q, setQ] = useState("");
  const [assigned, setAssigned] = useState<"all" | "0" | "1">("all");

  const listParams = useMemo(
    () => ({
      q: q.trim() || undefined,
      assigned: assigned === "all" ? undefined : assigned,
    }),
    [q, assigned],
  );

  const zonesQuery = useQuery({
    queryKey: queryKeys.cloudflareAdminZones({
      q: listParams.q ?? "",
      assigned: listParams.assigned ?? "",
    }),
    queryFn: () => fetchAdminCloudflareZones(listParams),
  });

  const orgsQuery = useQuery({
    queryKey: queryKeys.organizations({ include_trashed: false }),
    queryFn: async () => (await fetchOrganizations({ include_trashed: false })).data,
  });

  const syncMutation = useMutation({
    mutationFn: syncCloudflareZones,
    onSuccess: (data) => {
      toast.success(`Synced ${data.synced} zones (${data.created} new, ${data.updated} updated)`);
      void queryClient.invalidateQueries({ queryKey: ["cloudflare-admin-zones"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const assignMutation = useMutation({
    mutationFn: ({ id, organizationId }: { id: string; organizationId: string | null }) =>
      assignCloudflareZone(id, organizationId),
    onSuccess: () => {
      toast.success("Assignment updated");
      void queryClient.invalidateQueries({ queryKey: ["cloudflare-admin-zones"] });
      void queryClient.invalidateQueries({ queryKey: ["cloudflare-org-domains"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const items = zonesQuery.data?.items ?? [];
  const configured = zonesQuery.data?.configured ?? false;
  const organizations = orgsQuery.data ?? [];

  return (
    <AdminAccessGate>
      <div className="mx-auto flex max-w-6xl flex-col gap-6">
        <div className="space-y-4">
          <AdminNav />
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Cloudflare Domains</h1>
            <p className="text-sm text-muted-foreground">
              Option B: sync zones from your company Cloudflare account, then assign each domain to a
              client organization. Clients only see domains assigned to them.
            </p>
          </div>
        </div>

        {!configured ? (
          <Card className="border-amber-500/40 bg-amber-500/5">
            <CardHeader>
              <CardTitle className="text-base">Cloudflare API not configured</CardTitle>
              <CardDescription>
                Set <code className="rounded bg-muted px-1">CLOUDFLARE_API_TOKEN</code> (and optionally{" "}
                <code className="rounded bg-muted px-1">CLOUDFLARE_ACCOUNT_ID</code>) in{" "}
                <code className="rounded bg-muted px-1">.env</code> /{" "}
                <code className="rounded bg-muted px-1">.env.docker</code>, then recreate the app
                container.
              </CardDescription>
            </CardHeader>
          </Card>
        ) : null}

        <Card className="border-border/60">
          <CardHeader className="flex flex-row items-center justify-between gap-4">
            <div>
              <CardTitle className="flex items-center gap-2">
                <Globe className="h-5 w-5" />
                Zone catalog
              </CardTitle>
              <CardDescription>
                {items.length} domain{items.length === 1 ? "" : "s"} in VendorPulse
              </CardDescription>
            </div>
            <Button onClick={() => syncMutation.mutate()} disabled={!configured || syncMutation.isPending}>
              <RefreshCw className={`mr-2 h-4 w-4 ${syncMutation.isPending ? "animate-spin" : ""}`} />
              {syncMutation.isPending ? "Syncing..." : "Sync from Cloudflare"}
            </Button>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid gap-3 md:grid-cols-[1fr_180px]">
              <Input placeholder="Search domain..." value={q} onChange={(e) => setQ(e.target.value)} />
              <select
                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                value={assigned}
                onChange={(e) => setAssigned(e.target.value as typeof assigned)}
              >
                <option value="all">All</option>
                <option value="1">Assigned</option>
                <option value="0">Unassigned</option>
              </select>
            </div>

            {zonesQuery.isLoading ? (
              <p className="text-sm text-muted-foreground">Loading...</p>
            ) : items.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No zones yet. Configure the API token and click Sync from Cloudflare.
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="text-left text-xs text-muted-foreground">
                    <tr>
                      <th className="py-2 pr-3 font-medium">Domain</th>
                      <th className="py-2 pr-3 font-medium">Status</th>
                      <th className="py-2 pr-3 font-medium">Plan</th>
                      <th className="py-2 pr-3 font-medium">Assigned to</th>
                      <th className="py-2 font-medium">Assign</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((zone) => (
                      <tr key={zone.id} className="border-t border-border/60">
                        <td className="py-2.5 pr-3 font-medium">{zone.name}</td>
                        <td className="py-2.5 pr-3">
                          <Badge variant={zone.status === "active" ? "success" : "outline"}>
                            {zone.status ?? "unknown"}
                          </Badge>
                          {zone.paused ? <Badge variant="warning" className="ml-1">paused</Badge> : null}
                        </td>
                        <td className="py-2.5 pr-3 text-muted-foreground">{zone.plan_name ?? "—"}</td>
                        <td className="py-2.5 pr-3">{zone.organization_name ?? <span className="text-muted-foreground">Unassigned</span>}</td>
                        <td className="py-2.5">
                          <select
                            className="h-9 max-w-[220px] rounded-md border border-input bg-background px-2 text-sm"
                            value={zone.organization_id ?? ""}
                            disabled={assignMutation.isPending}
                            onChange={(e) => {
                              const value = e.target.value;
                              assignMutation.mutate({
                                id: zone.id,
                                organizationId: value === "" ? null : value,
                              });
                            }}
                          >
                            <option value="">Unassigned</option>
                            {organizations.map((org) => (
                              <option key={org.id} value={org.id}>
                                {org.name}
                              </option>
                            ))}
                          </select>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AdminAccessGate>
  );
}
