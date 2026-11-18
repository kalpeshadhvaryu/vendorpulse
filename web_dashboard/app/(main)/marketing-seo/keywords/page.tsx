"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowDown, ArrowLeft, ArrowUp, Loader2, Minus, Search, Sparkles } from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { getApiErrorMessage } from "@/lib/api/errors";
import {
  checkSeoKeyword,
  createSeoKeyword,
  deleteSeoKeyword,
  fetchSeoKeyword,
  fetchSeoKeywords,
  updateSeoKeyword,
} from "@/lib/api/marketing-seo";
import { queryKeys } from "@/lib/api/query-keys";
import type { SeoKeyword, SeoKeywordPriority, SeoKeywordStatus } from "@/lib/api/types";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

function priorityBadge(priority: SeoKeywordPriority) {
  if (priority === "high") return <Badge variant="destructive">High</Badge>;
  if (priority === "low") return <Badge variant="outline">Low</Badge>;
  return <Badge variant="secondary">Medium</Badge>;
}

function positionLabel(pos: number | null) {
  if (pos === null) return "—";
  return `#${pos}`;
}

function ChangeCell({ change }: { change: number | null }) {
  if (change === null) return <span className="text-muted-foreground">—</span>;
  if (change > 0) {
    return (
      <span className="inline-flex items-center gap-1 text-emerald-600">
        <ArrowUp className="h-3.5 w-3.5" />+{change}
      </span>
    );
  }
  if (change < 0) {
    return (
      <span className="inline-flex items-center gap-1 text-destructive">
        <ArrowDown className="h-3.5 w-3.5" />
        {change}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 text-muted-foreground">
      <Minus className="h-3.5 w-3.5" />0
    </span>
  );
}

export default function KeywordsPage() {
  const queryClient = useQueryClient();
  const organizationId = useAuthStore((s) => s.organizationId);
  const [q, setQ] = useState("");
  const [priority, setPriority] = useState<"all" | SeoKeywordPriority>("all");
  const [status, setStatus] = useState<"all" | SeoKeywordStatus>("all");
  const [selectedId, setSelectedId] = useState<string | null>(null);

  const [phrase, setPhrase] = useState("");
  const [newPriority, setNewPriority] = useState<SeoKeywordPriority>("medium");
  const [targetUrl, setTargetUrl] = useState("");
  const [country, setCountry] = useState("in");
  const [device, setDevice] = useState<"desktop" | "mobile">("desktop");

  const listParams = useMemo(
    () => ({
      q: q.trim() || undefined,
      priority: priority === "all" ? undefined : priority,
      status: status === "all" ? undefined : status,
    }),
    [q, priority, status],
  );

  const listQuery = useQuery({
    queryKey: queryKeys.seoKeywords(organizationId, {
      q: listParams.q ?? "",
      priority: listParams.priority ?? "",
      status: listParams.status ?? "",
    }),
    queryFn: () => fetchSeoKeywords(listParams),
    enabled: Boolean(organizationId),
  });

  const detailQuery = useQuery({
    queryKey: queryKeys.seoKeyword(selectedId ?? ""),
    queryFn: () => fetchSeoKeyword(selectedId as string),
    enabled: selectedId !== null,
  });

  const createMutation = useMutation({
    mutationFn: createSeoKeyword,
    onSuccess: () => {
      toast.success("Keyword added");
      setPhrase("");
      setTargetUrl("");
      void queryClient.invalidateQueries({ queryKey: ["seo-keywords"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const checkMutation = useMutation({
    mutationFn: checkSeoKeyword,
    onSuccess: (data) => {
      if (data.check.error) {
        toast.error(data.check.error);
      } else {
        toast.success("Rank check completed");
      }
      void queryClient.invalidateQueries({ queryKey: ["seo-keywords"] });
      if (selectedId) {
        void queryClient.invalidateQueries({ queryKey: queryKeys.seoKeyword(selectedId) });
      }
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const pauseMutation = useMutation({
    mutationFn: ({ id, status }: { id: string; status: SeoKeywordStatus }) =>
      updateSeoKeyword(id, { status }),
    onSuccess: () => {
      toast.success("Keyword updated");
      void queryClient.invalidateQueries({ queryKey: ["seo-keywords"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const deleteMutation = useMutation({
    mutationFn: deleteSeoKeyword,
    onSuccess: () => {
      toast.success("Keyword deleted");
      setSelectedId(null);
      void queryClient.invalidateQueries({ queryKey: ["seo-keywords"] });
    },
    onError: (error) => toast.error(getApiErrorMessage(error)),
  });

  const items = listQuery.data?.items ?? [];
  const providerConfigured = listQuery.data?.provider_configured ?? false;
  const provider = listQuery.data?.provider ?? "none";

  if (!organizationId) {
    return (
      <div className="mx-auto max-w-3xl py-10">
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle>Select an organization</CardTitle>
            <CardDescription>Keywords are stored per organization. Pick one from the top bar.</CardDescription>
          </CardHeader>
        </Card>
      </div>
    );
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div>
        <Button variant="ghost" size="sm" className="-ml-2 mb-1 gap-1 px-2" asChild>
          <Link href="/marketing-seo-hub">
            <ArrowLeft className="h-4 w-4" />
            Marketing &amp; SEO
          </Link>
        </Button>
        <h1 className="text-2xl font-semibold tracking-tight">Keywords</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Track priority phrases on Google and whether AI answers cite your brand.
        </p>
      </div>

      {!providerConfigured ? (
        <Card className="border-amber-500/40 bg-amber-500/5">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Rank provider not configured</CardTitle>
            <CardDescription>
              You can add and manage keywords now. To run live Google checks, set{" "}
              <code className="rounded bg-muted px-1">SEO_KEYWORDS_PROVIDER=serpapi</code> with{" "}
              <code className="rounded bg-muted px-1">SERPAPI_KEY</code>, or use{" "}
              <code className="rounded bg-muted px-1">google_cse</code> with a Programmable Search key.
              Current provider: <strong>{provider}</strong>.
            </CardDescription>
          </CardHeader>
        </Card>
      ) : null}

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5" />
            Add keyword
          </CardTitle>
          <CardDescription>High-priority keywords are checked more often once a provider is set.</CardDescription>
        </CardHeader>
        <CardContent>
          <form
            className="grid gap-3 md:grid-cols-[1.4fr_140px_1fr_100px_120px_auto]"
            onSubmit={(e) => {
              e.preventDefault();
              if (!phrase.trim()) return;
              createMutation.mutate({
                phrase: phrase.trim(),
                priority: newPriority,
                target_url: targetUrl.trim() || null,
                country,
                language: "en",
                device,
                status: "active",
              });
            }}
          >
            <Input placeholder="Keyword phrase" value={phrase} onChange={(e) => setPhrase(e.target.value)} />
            <select
              className="h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={newPriority}
              onChange={(e) => setNewPriority(e.target.value as SeoKeywordPriority)}
            >
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
            <Input
              placeholder="Target URL (your page)"
              value={targetUrl}
              onChange={(e) => setTargetUrl(e.target.value)}
            />
            <Input placeholder="Country" value={country} onChange={(e) => setCountry(e.target.value)} maxLength={2} />
            <select
              className="h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={device}
              onChange={(e) => setDevice(e.target.value as "desktop" | "mobile")}
            >
              <option value="desktop">Desktop</option>
              <option value="mobile">Mobile</option>
            </select>
            <Button type="submit" disabled={createMutation.isPending || !phrase.trim()}>
              {createMutation.isPending ? "Adding..." : "Add"}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle>Keyword list</CardTitle>
          <CardDescription>{items.length} keyword{items.length === 1 ? "" : "s"}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="grid gap-3 md:grid-cols-[1fr_160px_160px]">
            <div className="relative">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input className="pl-8" placeholder="Search phrases..." value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
            <select
              className="h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={priority}
              onChange={(e) => setPriority(e.target.value as typeof priority)}
            >
              <option value="all">All priorities</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
            <select
              className="h-9 rounded-md border border-input bg-background px-3 text-sm"
              value={status}
              onChange={(e) => setStatus(e.target.value as typeof status)}
            >
              <option value="all">All statuses</option>
              <option value="active">Active</option>
              <option value="paused">Paused</option>
            </select>
          </div>

          {listQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading...</p>
          ) : items.length === 0 ? (
            <p className="text-sm text-muted-foreground">No keywords yet. Add your first priority phrase above.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="py-2 pr-3 font-medium">Keyword</th>
                    <th className="py-2 pr-3 font-medium">Priority</th>
                    <th className="py-2 pr-3 font-medium">Google</th>
                    <th className="py-2 pr-3 font-medium">Change</th>
                    <th className="py-2 pr-3 font-medium">AI</th>
                    <th className="py-2 pr-3 font-medium">Status</th>
                    <th className="py-2 font-medium" />
                  </tr>
                </thead>
                <tbody>
                  {items.map((item: SeoKeyword) => (
                    <tr
                      key={item.id}
                      className={cn("border-t border-border/60", selectedId === item.id && "bg-muted/40")}
                    >
                      <td className="py-2.5 pr-3">
                        <button type="button" className="text-left font-medium hover:underline" onClick={() => setSelectedId(item.id)}>
                          {item.phrase}
                        </button>
                        {item.target_domain ? (
                          <p className="font-mono text-xs text-muted-foreground">{item.target_domain}</p>
                        ) : null}
                      </td>
                      <td className="py-2.5 pr-3">{priorityBadge(item.priority)}</td>
                      <td className="py-2.5 pr-3 tabular-nums">{positionLabel(item.current_google_position)}</td>
                      <td className="py-2.5 pr-3">
                        <ChangeCell change={item.position_change} />
                      </td>
                      <td className="py-2.5 pr-3">
                        {item.current_ai_cited === true ? (
                          <Badge variant="success">Cited</Badge>
                        ) : item.current_ai_cited === false ? (
                          <Badge variant="outline">Not cited</Badge>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </td>
                      <td className="py-2.5 pr-3">
                        <Badge variant={item.status === "active" ? "secondary" : "outline"}>{item.status}</Badge>
                      </td>
                      <td className="py-2.5 text-right whitespace-nowrap">
                        <Button
                          size="sm"
                          variant="ghost"
                          disabled={checkMutation.isPending}
                          onClick={() => checkMutation.mutate(item.id)}
                        >
                          {checkMutation.isPending && checkMutation.variables === item.id ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            "Check"
                          )}
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() =>
                            pauseMutation.mutate({
                              id: item.id,
                              status: item.status === "active" ? "paused" : "active",
                            })
                          }
                        >
                          {item.status === "active" ? "Pause" : "Resume"}
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

      {selectedId && detailQuery.data ? (
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle>{detailQuery.data.keyword.phrase}</CardTitle>
            <CardDescription>
              Last checked{" "}
              {detailQuery.data.keyword.last_checked_at
                ? new Date(detailQuery.data.keyword.last_checked_at).toLocaleString()
                : "never"}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div className="rounded-md border border-border/60 p-3">
                <p className="text-xs text-muted-foreground">Google position</p>
                <p className="text-xl font-semibold tabular-nums">
                  {positionLabel(detailQuery.data.keyword.current_google_position)}
                </p>
              </div>
              <div className="rounded-md border border-border/60 p-3">
                <p className="text-xs text-muted-foreground">Change</p>
                <p className="text-xl font-semibold">
                  <ChangeCell change={detailQuery.data.keyword.position_change} />
                </p>
              </div>
              <div className="rounded-md border border-border/60 p-3 sm:col-span-2">
                <p className="text-xs text-muted-foreground">Ranked URL</p>
                <p className="break-all font-mono text-xs">
                  {detailQuery.data.keyword.current_google_url ?? "—"}
                </p>
              </div>
            </div>

            <div>
              <p className="mb-2 text-sm font-medium">Recent checks</p>
              {detailQuery.data.checks.length === 0 ? (
                <p className="text-sm text-muted-foreground">No checks yet. Click Check on the list.</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="text-left text-xs text-muted-foreground">
                      <tr>
                        <th className="py-2 pr-3 font-medium">When</th>
                        <th className="py-2 pr-3 font-medium">Google</th>
                        <th className="py-2 pr-3 font-medium">AI</th>
                        <th className="py-2 pr-3 font-medium">Source</th>
                        <th className="py-2 font-medium">Notes</th>
                      </tr>
                    </thead>
                    <tbody>
                      {detailQuery.data.checks.map((check) => (
                        <tr key={check.id} className="border-t border-border/60 align-top">
                          <td className="py-2 pr-3 whitespace-nowrap">
                            {new Date(check.checked_at).toLocaleString()}
                          </td>
                          <td className="py-2 pr-3 tabular-nums">{positionLabel(check.google_position)}</td>
                          <td className="py-2 pr-3">
                            {(check.ai_results ?? [])
                              .filter((r) => r.status !== "not_checked")
                              .map((r) => `${r.engine}: ${r.status}`)
                              .join(", ") || "—"}
                          </td>
                          <td className="py-2 pr-3">{check.source}</td>
                          <td className="py-2 text-xs text-muted-foreground">{check.error ?? "OK"}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            <div className="flex flex-wrap gap-2">
              <Button onClick={() => checkMutation.mutate(selectedId)} disabled={checkMutation.isPending}>
                Check now
              </Button>
              <Button
                variant="outline"
                onClick={() =>
                  pauseMutation.mutate({
                    id: selectedId,
                    status: detailQuery.data.keyword.status === "active" ? "paused" : "active",
                  })
                }
              >
                {detailQuery.data.keyword.status === "active" ? "Pause" : "Resume"}
              </Button>
              <Button
                variant="destructive"
                onClick={() => {
                  if (confirm("Delete this keyword and its history?")) {
                    deleteMutation.mutate(selectedId);
                  }
                }}
              >
                Delete
              </Button>
              <Button variant="ghost" onClick={() => setSelectedId(null)}>
                Close
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
