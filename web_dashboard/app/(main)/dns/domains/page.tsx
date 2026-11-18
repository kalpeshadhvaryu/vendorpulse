"use client";

import { useState } from "react";
import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Globe, Loader2, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { createDnsDomain, deleteDnsDomain, fetchDnsDomains } from "@/lib/api/dns";
import { getApiErrorMessage } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore } from "@/stores/auth-store";

export default function DnsDomainsPage() {
  const queryClient = useQueryClient();
  const organizationId = useAuthStore((s) => s.organizationId);
  const [provider, setProvider] = useState<"cloudflare" | "manual">("manual");
  const [name, setName] = useState("");
  const [cloudflareZoneId, setCloudflareZoneId] = useState("");
  const [notes, setNotes] = useState("");

  const listQuery = useQuery({
    queryKey: queryKeys.dnsDomains(organizationId),
    queryFn: fetchDnsDomains,
    enabled: Boolean(organizationId),
  });

  const createMutation = useMutation({
    mutationFn: createDnsDomain,
    onSuccess: () => {
      toast.success("DNS domain added");
      setName("");
      setNotes("");
      setCloudflareZoneId("");
      void queryClient.invalidateQueries({ queryKey: ["dns-domains"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const deleteMutation = useMutation({
    mutationFn: deleteDnsDomain,
    onSuccess: () => {
      toast.success("DNS domain removed");
      void queryClient.invalidateQueries({ queryKey: ["dns-domains"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const items = listQuery.data?.items ?? [];
  const cfZones = listQuery.data?.available_cloudflare_zones ?? [];

  if (!organizationId) {
    return (
      <div className="mx-auto max-w-3xl py-10">
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle>Select an organization</CardTitle>
            <CardDescription>DNS management is scoped to the selected client organization.</CardDescription>
          </CardHeader>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">DNS Management</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Manage DNS records for Cloudflare domains (live) or non-Cloudflare domains (stored in
          VendorPulse).
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="text-base">Add domain</CardTitle>
          <CardDescription>
            Choose Cloudflare to edit live DNS via API, or Manual for domains hosted elsewhere
            (records kept in VendorPulse).
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant={provider === "manual" ? "default" : "outline"}
              onClick={() => setProvider("manual")}
            >
              Without Cloudflare
            </Button>
            <Button
              type="button"
              size="sm"
              variant={provider === "cloudflare" ? "default" : "outline"}
              onClick={() => setProvider("cloudflare")}
            >
              Cloudflare
            </Button>
          </div>

          {provider === "manual" ? (
            <Input
              placeholder="example.com"
              value={name}
              onChange={(e) => setName(e.target.value)}
            />
          ) : (
            <select
              className="h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={cloudflareZoneId}
              onChange={(e) => setCloudflareZoneId(e.target.value)}
            >
              <option value="">Select assigned Cloudflare domain…</option>
              {cfZones.map((z) => (
                <option key={z.id} value={z.id}>
                  {z.name}
                </option>
              ))}
            </select>
          )}

          <Input
            placeholder="Notes (optional)"
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
          />

          <div>
            <Button
              disabled={createMutation.isPending}
              onClick={() => {
                if (provider === "manual") {
                  createMutation.mutate({
                    provider: "manual",
                    name: name.trim(),
                    notes: notes.trim() || undefined,
                  });
                } else {
                  createMutation.mutate({
                    provider: "cloudflare",
                    cloudflare_zone_id: cloudflareZoneId,
                    notes: notes.trim() || undefined,
                  });
                }
              }}
            >
              {createMutation.isPending ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Plus className="mr-2 h-4 w-4" />
              )}
              Add domain
            </Button>
          </div>

          {provider === "cloudflare" && cfZones.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No Cloudflare domains assigned yet. An admin must sync and assign under Administration
              → Cloudflare Domains.
            </p>
          ) : null}
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Globe className="h-5 w-5" />
            Domains
          </CardTitle>
          <CardDescription>
            {items.length} domain{items.length === 1 ? "" : "s"}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {listQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading...</p>
          ) : items.length === 0 ? (
            <p className="text-sm text-muted-foreground">No DNS domains yet. Add one above.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="py-2 pr-3 font-medium">Domain</th>
                    <th className="py-2 pr-3 font-medium">Provider</th>
                    <th className="py-2 pr-3 font-medium">Notes</th>
                    <th className="py-2 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((domain) => (
                    <tr key={domain.id} className="border-t border-border/60">
                      <td className="py-2.5 pr-3 font-medium">
                        <Link
                          href={`/dns/domains/${domain.id}`}
                          className="text-foreground underline-offset-4 hover:underline"
                        >
                          {domain.name}
                        </Link>
                      </td>
                      <td className="py-2.5 pr-3">
                        <Badge variant={domain.provider === "cloudflare" ? "success" : "outline"}>
                          {domain.provider === "cloudflare" ? "Cloudflare" : "Manual"}
                        </Badge>
                      </td>
                      <td className="py-2.5 pr-3 text-muted-foreground">
                        {domain.notes?.trim() || "—"}
                      </td>
                      <td className="py-2.5 whitespace-nowrap">
                        <Button asChild size="sm" variant="ghost">
                          <Link href={`/dns/domains/${domain.id}`}>Manage records</Link>
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          disabled={deleteMutation.isPending}
                          onClick={() => {
                            if (
                              confirm(
                                `Remove ${domain.name} from VendorPulse DNS? ${
                                  domain.provider === "cloudflare"
                                    ? "Cloudflare DNS itself is not deleted."
                                    : "Stored records will be deleted."
                                }`,
                              )
                            ) {
                              deleteMutation.mutate(domain.id);
                            }
                          }}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
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
