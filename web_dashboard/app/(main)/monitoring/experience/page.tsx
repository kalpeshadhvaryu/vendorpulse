"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Play, Trash2, Pencil } from "lucide-react";
import { toast } from "sonner";
import { useExperienceMonitoringTests } from "@/hooks/use-experience-monitoring";
import {
  deleteExperienceMonitoringTest,
  triggerExperienceMonitoringTest,
} from "@/lib/api/experience-monitoring";
import { getApiErrorMessage } from "@/lib/api/errors";
import { queryKeys } from "@/lib/api/query-keys";
import type { ExperienceMonitoringTest } from "@/lib/api/types";
import { ExperienceMonitoringUpsertSheet } from "@/components/monitoring/experience-monitoring-upsert-sheet";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";

function statusVariant(status: string | null): "success" | "warning" | "destructive" | "secondary" {
  const value = (status ?? "").toLowerCase();
  if (value === "ok") return "success";
  if (value === "slow_dashboard" || value === "js_error") return "warning";
  if (value === "failed_login" || value === "timeout" || value === "error") return "destructive";
  return "secondary";
}

export default function ExperienceMonitoringPage() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [upsertOpen, setUpsertOpen] = useState(false);
  const [editing, setEditing] = useState<ExperienceMonitoringTest | null>(null);

  const params = useMemo(() => ({ per_page: "50", search }), [search]);
  const testsQuery = useExperienceMonitoringTests(params);

  const triggerMutation = useMutation({
    mutationFn: (id: string) => triggerExperienceMonitoringTest(id),
    onSuccess: () => {
      toast.success("Experience test queued — waiting for Horizon");
      void queryClient.invalidateQueries({ queryKey: queryKeys.experienceMonitoringTests({}) });
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteExperienceMonitoringTest(id),
    onSuccess: () => {
      toast.success("Experience test deleted");
      void queryClient.invalidateQueries({ queryKey: queryKeys.experienceMonitoringTests({}) });
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Experience Monitoring</h1>
          <p className="text-sm text-muted-foreground">
            Browser-based login and dashboard experience checks with queued execution.
          </p>
        </div>
        <Button
          type="button"
          onClick={() => {
            setEditing(null);
            setUpsertOpen(true);
          }}
        >
          <Plus className="h-4 w-4" />
          Create Test
        </Button>
      </div>

      <Card className="border-border/60">
        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <CardTitle>Tests</CardTitle>
            <CardDescription>
              Monitoring - Experience Monitoring list with status badges, trigger, and drill-down history.
            </CardDescription>
          </div>
          <Input
            placeholder="Search by test name, login url, dashboard..."
            className="w-full sm:w-[320px]"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Test</TableHead>
                <TableHead>Interval</TableHead>
                <TableHead>Sessions</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Last Run</TableHead>
                <TableHead className="w-[190px] text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {testsQuery.isLoading ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                    Loading experience tests...
                  </TableCell>
                </TableRow>
              ) : testsQuery.isError ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-sm text-destructive">
                    {getApiErrorMessage(testsQuery.error)}
                  </TableCell>
                </TableRow>
              ) : (testsQuery.data?.items.length ?? 0) === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                    No experience monitoring tests yet.
                  </TableCell>
                </TableRow>
              ) : (
                testsQuery.data?.items.map((test) => (
                  <TableRow key={test.id}>
                    <TableCell>
                      <div className="space-y-0.5">
                        <p className="font-medium">{test.name}</p>
                        <p className="max-w-[320px] truncate text-xs text-muted-foreground">{test.dashboard_url}</p>
                      </div>
                    </TableCell>
                    <TableCell>{test.interval_seconds}s</TableCell>
                    <TableCell>{test.concurrent_sessions ?? 1}</TableCell>
                    <TableCell>
                      <div className="space-y-1">
                        <Badge variant={statusVariant(test.last_status)}>{test.last_status ?? "pending"}</Badge>
                        {test.last_error ? (
                          <p className="max-w-[240px] truncate text-xs text-destructive" title={test.last_error}>
                            {test.last_error}
                          </p>
                        ) : null}
                      </div>
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {test.last_run_at ? new Date(test.last_run_at).toLocaleString() : "Never"}
                    </TableCell>
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        <Button
                          type="button"
                          size="icon"
                          variant="outline"
                          title="Trigger now"
                          onClick={() => triggerMutation.mutate(test.id)}
                        >
                          <Play className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          type="button"
                          size="icon"
                          variant="outline"
                          title="Edit"
                          onClick={() => {
                            setEditing(test);
                            setUpsertOpen(true);
                          }}
                        >
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          type="button"
                          size="icon"
                          variant="outline"
                          title="Delete"
                          onClick={() => {
                            deleteMutation.mutate(test.id);
                          }}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                        <Button type="button" variant="outline" asChild>
                          <Link href={`/monitoring/experience/${test.id}`}>History</Link>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <ExperienceMonitoringUpsertSheet open={upsertOpen} onOpenChange={setUpsertOpen} test={editing} />
    </div>
  );
}
