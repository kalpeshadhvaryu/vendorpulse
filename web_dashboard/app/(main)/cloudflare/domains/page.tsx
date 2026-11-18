"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Globe, Loader2, RefreshCw, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  fetchOrgCloudflareDomains,
  purgeCloudflareDomainCache,
  refreshCloudflareDomain,
} from "@/lib/api/cloudflare";
import { getApiErrorMessage } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore } from "@/stores/auth-store";

export default function OrgCloudflareDomainsPage() {
  const queryClient = useQueryClient();
  const organizationId = useAuthStore((s) => s.organizationId);

  const listQuery = useQuery({
    queryKey: queryKeys.cloudflareOrgDomains(organizationId),
    queryFn: fetchOrgCloudflareDomains,
    enabled: Boolean(organizationId),
  });

  const refreshMutation = useMutation({
    mutationFn: refreshCloudflareDomain,
    onSuccess: () => {
      toast.success("Status refreshed");
      void queryClient.invalidateQueries({ queryKey: ["cloudflare-org-domains"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const purgeMutation = useMutation({
    mutationFn: purgeCloudflareDomainCache,
    onSuccess: () => toast.success("Cache purge requested"),
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const items = listQuery.data?.items ?? [];
  const allowPurge = listQuery.data?.allow_org_purge_cache ?? false;

  if (!organizationId) {
    return (
      <div className="mx-auto max-w-3xl py-10">
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle>Select an organization</CardTitle>
            <CardDescription>
              Cloudflare domains are shown for the selected client organization.
            </CardDescription>
          </CardHeader>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">My Cloudflare Domains</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Domains from our Cloudflare account that are assigned to your organization. You do not need a
          separate Cloudflare login.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Globe className="h-5 w-5" />
            Assigned domains
          </CardTitle>
          <CardDescription>
            {items.length} domain{items.length === 1 ? "" : "s"}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {listQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading...</p>
          ) : items.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No Cloudflare domains are assigned to this organization yet. Ask an administrator to sync
              and assign domains under Administration → Cloudflare Domains.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="py-2 pr-3 font-medium">Domain</th>
                    <th className="py-2 pr-3 font-medium">Status</th>
                    <th className="py-2 pr-3 font-medium">SSL</th>
                    <th className="py-2 pr-3 font-medium">Nameservers</th>
                    <th className="py-2 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((zone) => (
                    <tr key={zone.id} className="border-t border-border/60 align-top">
                      <td className="py-2.5 pr-3 font-medium">{zone.name}</td>
                      <td className="py-2.5 pr-3">
                        <Badge variant={zone.status === "active" ? "success" : "outline"}>
                          {zone.status ?? "unknown"}
                        </Badge>
                      </td>
                      <td className="py-2.5 pr-3">{zone.ssl_mode ?? "—"}</td>
                      <td className="py-2.5 pr-3 font-mono text-xs">
                        {(zone.name_servers ?? []).join(", ") || "—"}
                      </td>
                      <td className="py-2.5 whitespace-nowrap">
                        <Button
                          size="sm"
                          variant="ghost"
                          disabled={refreshMutation.isPending}
                          onClick={() => refreshMutation.mutate(zone.id)}
                        >
                          {refreshMutation.isPending && refreshMutation.variables === zone.id ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <>
                              <RefreshCw className="mr-1 h-3.5 w-3.5" />
                              Refresh
                            </>
                          )}
                        </Button>
                        {allowPurge ? (
                          <Button
                            size="sm"
                            variant="ghost"
                            disabled={purgeMutation.isPending}
                            onClick={() => {
                              if (confirm(`Purge Cloudflare cache for ${zone.name}?`)) {
                                purgeMutation.mutate(zone.id);
                              }
                            }}
                          >
                            <Trash2 className="mr-1 h-3.5 w-3.5" />
                            Purge cache
                          </Button>
                        ) : null}
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
  );
}
