"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { MoreHorizontal, Plus, Search } from "lucide-react";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchVendors } from "@/lib/api/vendors";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { Vendor } from "@/lib/api/types";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { VendorDeleteSheet } from "@/components/vendors/vendor-delete-sheet";
import { VendorUpsertSheet } from "@/components/vendors/vendor-upsert-sheet";
import { useDashboardSettings } from "@/stores/dashboard-settings-store";

export default function VendorsPage() {
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 350);
  const vendorsPerPage = useDashboardSettings((s) => s.vendorsPerPage);

  const [upsertOpen, setUpsertOpen] = useState(false);
  const [upsertVendor, setUpsertVendor] = useState<Vendor | null>(null);

  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleteVendorRow, setDeleteVendorRow] = useState<Vendor | null>(null);

  const query = useQuery({
    queryKey: queryKeys.vendors({ search: debouncedSearch, per_page: String(vendorsPerPage) }),
    queryFn: () => fetchVendors({ search: debouncedSearch || undefined, per_page: vendorsPerPage }),
  });

  function openCreate() {
    setUpsertVendor(null);
    setUpsertOpen(true);
  }

  function openEdit(v: Vendor) {
    setUpsertVendor(v);
    setUpsertOpen(true);
  }

  function openDelete(v: Vendor) {
    setDeleteVendorRow(v);
    setDeleteOpen(true);
  }

  return (
    <div className="mx-auto flex max-w-7xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Vendors</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Search and manage vendors for the active organization (Laravel scoped).
        </p>
      </div>
      <Card className="border-border/60">
        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <CardTitle>Directory</CardTitle>
            <CardDescription>Filters run against the VendorPulse API.</CardDescription>
          </div>
          <div className="flex w-full flex-col gap-3 sm:max-w-md sm:flex-row sm:items-center sm:justify-end">
            <div className="relative w-full sm:max-w-xs">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search name, email, website…"
                className="pl-9"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            <Button type="button" className="shrink-0" onClick={openCreate}>
              <Plus className="size-4" />
              Add vendor
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {query.isLoading ? (
            <Skeleton className="h-64 w-full" />
          ) : query.isError ? (
            <p className="text-sm text-destructive">{getApiErrorMessage(query.error)}</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Renewal</TableHead>
                  <TableHead className="text-right">Monitoring</TableHead>
                  <TableHead className="w-[52px] text-right" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {query.data?.items.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                      No vendors found.
                    </TableCell>
                  </TableRow>
                ) : (
                  query.data?.items.map((v) => (
                    <TableRow key={v.id}>
                      <TableCell className="font-medium">{v.name}</TableCell>
                      <TableCell className="text-muted-foreground">{v.vendor_type}</TableCell>
                      <TableCell>
                        <Badge variant="outline">{v.status}</Badge>
                      </TableCell>
                      <TableCell>{v.renewal_date ?? "—"}</TableCell>
                      <TableCell className="text-right">
                        <Badge variant={v.monitoring_enabled ? "success" : "secondary"}>
                          {v.monitoring_enabled ? "On" : "Off"}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button type="button" variant="ghost" size="icon" className="h-8 w-8">
                              <span className="sr-only">Open menu</span>
                              <MoreHorizontal className="size-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={() => openEdit(v)}>Edit</DropdownMenuItem>
                            <DropdownMenuItem
                              className="text-destructive focus:text-destructive"
                              onClick={() => openDelete(v)}
                            >
                              Delete
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <VendorUpsertSheet open={upsertOpen} onOpenChange={setUpsertOpen} vendor={upsertVendor} />

      <VendorDeleteSheet
        vendor={deleteVendorRow}
        open={deleteOpen}
        onOpenChange={(open) => {
          setDeleteOpen(open);
          if (!open) setDeleteVendorRow(null);
        }}
      />
    </div>
  );
}
