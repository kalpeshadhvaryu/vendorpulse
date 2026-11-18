"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Loader2, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  createDnsRecord,
  deleteDnsRecord,
  fetchDnsRecords,
  updateDnsRecord,
  type DnsRecord,
} from "@/lib/api/dns";
import { getApiErrorMessage } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore } from "@/stores/auth-store";

const RECORD_TYPES = ["A", "AAAA", "CNAME", "MX", "TXT", "NS", "SRV", "CAA"] as const;

const emptyForm = {
  type: "A",
  name: "@",
  content: "",
  ttl: "3600",
  priority: "10",
  proxied: false,
  comment: "",
};

export default function DnsDomainDetailPage() {
  const params = useParams<{ id: string }>() ?? { id: "" };
  const domainId = String(params.id ?? "");
  const queryClient = useQueryClient();
  const organizationId = useAuthStore((s) => s.organizationId);
  const [form, setForm] = useState(emptyForm);
  const [editing, setEditing] = useState<DnsRecord | null>(null);

  const recordsQuery = useQuery({
    queryKey: queryKeys.dnsRecords(domainId),
    queryFn: () => fetchDnsRecords(domainId),
    enabled: Boolean(organizationId) && Boolean(domainId),
  });

  const domain = recordsQuery.data?.domain;
  const items = recordsQuery.data?.items ?? [];
  const isCloudflare = domain?.provider === "cloudflare";

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.dnsRecords(domainId) });
  };

  const createMutation = useMutation({
    mutationFn: () =>
      createDnsRecord(domainId, {
        type: form.type,
        name: form.name.trim(),
        content: form.content.trim(),
        ttl: Number(form.ttl) || 3600,
        priority: form.type === "MX" ? Number(form.priority) || 10 : null,
        proxied: isCloudflare ? form.proxied : null,
        comment: form.comment.trim() || null,
      }),
    onSuccess: () => {
      toast.success("Record created");
      setForm(emptyForm);
      invalidate();
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const updateMutation = useMutation({
    mutationFn: () => {
      if (!editing) throw new Error("No record selected");
      return updateDnsRecord(domainId, editing.id, {
        type: form.type,
        name: form.name.trim(),
        content: form.content.trim(),
        ttl: Number(form.ttl) || 3600,
        priority: form.type === "MX" ? Number(form.priority) || 10 : null,
        proxied: isCloudflare ? form.proxied : null,
        comment: form.comment.trim() || null,
      });
    },
    onSuccess: () => {
      toast.success("Record updated");
      setEditing(null);
      setForm(emptyForm);
      invalidate();
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const deleteMutation = useMutation({
    mutationFn: (recordId: string) => deleteDnsRecord(domainId, recordId),
    onSuccess: () => {
      toast.success("Record deleted");
      invalidate();
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const title = useMemo(() => domain?.name ?? "DNS records", [domain?.name]);

  if (!organizationId) {
    return (
      <div className="mx-auto max-w-3xl py-10">
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle>Select an organization</CardTitle>
          </CardHeader>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div className="flex flex-wrap items-center gap-3">
        <Button asChild variant="ghost" size="sm">
          <Link href="/dns/domains">
            <ArrowLeft className="mr-1 h-4 w-4" />
            Back
          </Link>
        </Button>
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {isCloudflare
              ? "Live Cloudflare DNS — changes apply immediately on Cloudflare."
              : "Manual DNS inventory — stored in VendorPulse (does not push to a nameserver)."}
          </p>
        </div>
        {domain ? (
          <Badge variant={isCloudflare ? "success" : "outline"} className="ml-auto">
            {isCloudflare ? "Cloudflare" : "Manual"}
          </Badge>
        ) : null}
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="text-base">{editing ? "Edit record" : "Add record"}</CardTitle>
          <CardDescription>
            {isCloudflare
              ? "TTL 1 = Cloudflare Auto. Proxied only applies to Cloudflare."
              : "Use this as your ops inventory for domains not on Cloudflare."}
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <select
            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
            value={form.type}
            onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
          >
            {RECORD_TYPES.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </select>
          <Input
            placeholder="Name (@ or subdomain)"
            value={form.name}
            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
          />
          <Input
            placeholder="Content / value"
            value={form.content}
            onChange={(e) => setForm((f) => ({ ...f, content: e.target.value }))}
          />
          <Input
            placeholder="TTL"
            value={form.ttl}
            onChange={(e) => setForm((f) => ({ ...f, ttl: e.target.value }))}
          />
          {form.type === "MX" ? (
            <Input
              placeholder="Priority"
              value={form.priority}
              onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))}
            />
          ) : null}
          {isCloudflare ? (
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.proxied}
                onChange={(e) => setForm((f) => ({ ...f, proxied: e.target.checked }))}
              />
              Proxied (orange cloud)
            </label>
          ) : null}
          <Input
            className="sm:col-span-2"
            placeholder="Comment (optional)"
            value={form.comment}
            onChange={(e) => setForm((f) => ({ ...f, comment: e.target.value }))}
          />
          <div className="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
            <Button
              disabled={createMutation.isPending || updateMutation.isPending}
              onClick={() => (editing ? updateMutation.mutate() : createMutation.mutate())}
            >
              {(createMutation.isPending || updateMutation.isPending) && (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              )}
              {!editing && <Plus className="mr-2 h-4 w-4" />}
              {editing ? "Save changes" : "Add record"}
            </Button>
            {editing ? (
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setEditing(null);
                  setForm(emptyForm);
                }}
              >
                Cancel
              </Button>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="text-base">Records</CardTitle>
          <CardDescription>
            {items.length} record{items.length === 1 ? "" : "s"}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {recordsQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading...</p>
          ) : recordsQuery.isError ? (
            <p className="text-sm text-destructive">{getApiErrorMessage(recordsQuery.error)}</p>
          ) : items.length === 0 ? (
            <p className="text-sm text-muted-foreground">No records yet.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="py-2 pr-3 font-medium">Type</th>
                    <th className="py-2 pr-3 font-medium">Name</th>
                    <th className="py-2 pr-3 font-medium">Content</th>
                    <th className="py-2 pr-3 font-medium">TTL</th>
                    <th className="py-2 pr-3 font-medium">Priority</th>
                    {isCloudflare ? <th className="py-2 pr-3 font-medium">Proxy</th> : null}
                    <th className="py-2 font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((record) => (
                    <tr key={record.id} className="border-t border-border/60 align-top">
                      <td className="py-2.5 pr-3 font-mono text-xs">{record.type}</td>
                      <td className="py-2.5 pr-3 font-mono text-xs">{record.name}</td>
                      <td className="max-w-xs break-all py-2.5 pr-3 font-mono text-xs">
                        {record.content}
                      </td>
                      <td className="py-2.5 pr-3">{record.ttl === 1 ? "Auto" : record.ttl}</td>
                      <td className="py-2.5 pr-3">{record.priority ?? "—"}</td>
                      {isCloudflare ? (
                        <td className="py-2.5 pr-3">{record.proxied ? "Yes" : "No"}</td>
                      ) : null}
                      <td className="py-2.5 whitespace-nowrap">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => {
                            setEditing(record);
                            setForm({
                              type: record.type,
                              name: record.name,
                              content: record.content,
                              ttl: String(record.ttl),
                              priority: String(record.priority ?? 10),
                              proxied: Boolean(record.proxied),
                              comment: record.comment ?? "",
                            });
                          }}
                        >
                          Edit
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          disabled={deleteMutation.isPending}
                          onClick={() => {
                            if (confirm(`Delete ${record.type} ${record.name}?`)) {
                              deleteMutation.mutate(record.id);
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
