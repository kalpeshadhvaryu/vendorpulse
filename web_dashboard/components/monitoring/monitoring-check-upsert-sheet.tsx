"use client";

import { useEffect, useMemo } from "react";
import Link from "next/link";
import { Controller, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { z } from "zod";
import {
  MONITORING_CHECK_TYPES,
  MONITORING_CHECK_TYPES_REQUIRING_ENDPOINT,
  createMonitoringCheck,
  updateMonitoringCheck,
} from "@/lib/api/monitoring";
import { fetchVendors } from "@/lib/api/vendors";
import { queryKeys } from "@/lib/api/query-keys";
import { getApiErrorMessage } from "@/lib/api/errors";
import type { MonitoringCheck } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

const textareaClass = cn(
  "flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm text-foreground shadow-sm transition-colors",
  "placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
  "disabled:cursor-not-allowed disabled:opacity-50",
);

const selectClass = cn(
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-sm",
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
);

type ServerProvider = "whm" | "cpanel" | "plesk" | "generic";

const SERVER_PROVIDER_PRESETS: Record<ServerProvider, Partial<MonitoringCheckFormValues>> = {
  whm: {
    server_auth_type: "whm_token",
    server_api_path: "json-api/loadavg",
    metrics_path_load_1m: "data.one",
    metrics_path_load_5m: "data.five",
    metrics_path_load_15m: "data.fifteen",
    metrics_path_cpu_percent: "data.cpu_percent",
    metrics_path_memory_used_percent: "data.memory.used_percent",
    metrics_path_disk_used_percent: "data.disk.used_percent",
    metrics_path_bandwidth_used_bytes: "data.bandwidth.used_bytes",
    metrics_path_process_count: "data.processes.count",
  },
  cpanel: {
    server_auth_type: "cpanel_token",
    server_api_path: "execute/ResourceUsage/get_usages",
    metrics_path_load_1m: "data.one",
    metrics_path_load_5m: "data.five",
    metrics_path_load_15m: "data.fifteen",
    metrics_path_cpu_percent: "data.cpu_percent",
    metrics_path_memory_used_percent: "data.memory.used_percent",
    metrics_path_disk_used_percent: "data.disk.used_percent",
    metrics_path_bandwidth_used_bytes: "data.bandwidth.used_bytes",
    metrics_path_process_count: "data.processes.count",
  },
  plesk: {
    server_auth_type: "bearer",
    server_api_path: "api/v2/server",
    metrics_path_load_1m: "result.metrics.load_1m",
    metrics_path_load_5m: "result.metrics.load_5m",
    metrics_path_load_15m: "result.metrics.load_15m",
    metrics_path_cpu_percent: "result.metrics.cpu_percent",
    metrics_path_memory_used_percent: "result.metrics.memory_used_percent",
    metrics_path_disk_used_percent: "result.metrics.disk_used_percent",
    metrics_path_bandwidth_used_bytes: "result.metrics.bandwidth_used_bytes",
    metrics_path_process_count: "result.metrics.process_count",
  },
  generic: {
    server_auth_type: "bearer",
    server_api_path: "metrics",
    metrics_path_load_1m: "metrics.load_1m",
    metrics_path_load_5m: "metrics.load_5m",
    metrics_path_load_15m: "metrics.load_15m",
    metrics_path_cpu_percent: "metrics.cpu_percent",
    metrics_path_memory_used_percent: "metrics.memory_used_percent",
    metrics_path_disk_used_percent: "metrics.disk_used_percent",
    metrics_path_bandwidth_used_bytes: "metrics.bandwidth_used_bytes",
    metrics_path_process_count: "metrics.process_count",
  },
};

const formSchema = z
  .object({
    name: z.string().min(1, "Name is required").max(255),
    type: z.string().min(1, "Type is required"),
    endpoint: z.string(),
    server_provider: z.string(),
    server_os: z.string(),
    server_hostname: z.string(),
    server_api_base_url: z.string(),
    server_api_path: z.string(),
    server_auth_type: z.string(),
    server_api_username: z.string(),
    server_api_token: z.string(),
    server_api_password: z.string(),
    server_verify_ssl: z.boolean(),
    tcp_port: z.coerce.number().int().min(1, "Port must be 1-65535").max(65535, "Port must be 1-65535"),
    tcp_timeout_ms: z.coerce.number().int().min(500, "Min timeout is 500ms").max(60000, "Max timeout is 60000ms"),
    metrics_path_load_1m: z.string(),
    metrics_path_load_5m: z.string(),
    metrics_path_load_15m: z.string(),
    metrics_path_cpu_percent: z.string(),
    metrics_path_memory_used_percent: z.string(),
    metrics_path_disk_used_percent: z.string(),
    metrics_path_bandwidth_used_bytes: z.string(),
    metrics_path_process_count: z.string(),
    interval_seconds: z.coerce.number().int().min(60, "Min 60 seconds").max(86400, "Max 24 hours"),
    enabled: z.boolean(),
    vendor_id: z.string(),
    configurationJson: z.string(),
  })
  .superRefine((data, ctx) => {
    if (MONITORING_CHECK_TYPES_REQUIRING_ENDPOINT.has(data.type) && !data.endpoint.trim()) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Endpoint is required for this check type (URL or host).",
        path: ["endpoint"],
      });
    }
    if ((data.type === "tcp" || data.type === "ping") && !data.endpoint.trim()) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Endpoint (IP or domain) is required for TCP checks.",
        path: ["endpoint"],
      });
    }
    if (data.type === "server") {
      if (!data.server_os.trim()) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "OS is required for server monitors.",
          path: ["server_os"],
        });
      }
      if (!data.server_hostname.trim()) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Hostname is required for server monitors.",
          path: ["server_hostname"],
        });
      }
      if (!data.server_api_base_url.trim()) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "API base URL is required for server monitors.",
          path: ["server_api_base_url"],
        });
      }
      if (data.server_auth_type === "cpanel_token") {
        if (!data.server_api_username.trim()) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: "API username is required for cPanel token auth.",
            path: ["server_api_username"],
          });
        }
      }
    }
    const trimmed = data.configurationJson.trim();
    if (trimmed) {
      try {
        JSON.parse(trimmed) as unknown;
      } catch {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: "Configuration must be valid JSON.",
          path: ["configurationJson"],
        });
      }
    }
  });

export type MonitoringCheckFormValues = z.infer<typeof formSchema>;

const emptyDefaults: MonitoringCheckFormValues = {
  name: "",
  type: "https",
  endpoint: "",
  server_provider: "whm",
  server_os: "linux",
  server_hostname: "",
  server_api_base_url: "",
  server_api_path: "",
  server_auth_type: "whm_token",
  server_api_username: "",
  server_api_token: "",
  server_api_password: "",
  server_verify_ssl: true,
  tcp_port: 80,
  tcp_timeout_ms: 5000,
  metrics_path_load_1m: "",
  metrics_path_load_5m: "",
  metrics_path_load_15m: "",
  metrics_path_cpu_percent: "",
  metrics_path_memory_used_percent: "",
  metrics_path_disk_used_percent: "",
  metrics_path_bandwidth_used_bytes: "",
  metrics_path_process_count: "",
  interval_seconds: 300,
  enabled: true,
  vendor_id: "",
  configurationJson: "{}",
};

function checkToFormValues(c: MonitoringCheck): MonitoringCheckFormValues {
  const cfg = (c.configuration ?? {}) as Record<string, unknown>;
  let configurationJson = "{}";
  if (cfg !== null && cfg !== undefined) {
    try {
      configurationJson = JSON.stringify(cfg, null, 2);
    } catch {
      configurationJson = String(cfg);
    }
  }

  const metricsPaths = (cfg.metrics_paths ?? {}) as Record<string, unknown>;

  return {
    name: c.name,
    type: c.type,
    endpoint: c.endpoint ?? "",
    server_provider: String(cfg.server_provider ?? "cpanel"),
    server_os: String(cfg.os ?? "linux"),
    server_hostname: String(cfg.hostname ?? ""),
    server_api_base_url: String(cfg.api_base_url ?? ""),
    server_api_path: String(cfg.api_path ?? ""),
    server_auth_type: String(cfg.auth_type ?? "cpanel_token"),
    server_api_username: String(cfg.api_username ?? ""),
    server_api_token: "",
    server_api_password: "",
    server_verify_ssl: cfg.verify_ssl == null ? true : Boolean(cfg.verify_ssl),
    tcp_port: Number(cfg.port ?? 80),
    tcp_timeout_ms: Number(cfg.timeout_ms ?? 5000),
    metrics_path_load_1m: String(metricsPaths.load_1m ?? ""),
    metrics_path_load_5m: String(metricsPaths.load_5m ?? ""),
    metrics_path_load_15m: String(metricsPaths.load_15m ?? ""),
    metrics_path_cpu_percent: String(metricsPaths.cpu_percent ?? ""),
    metrics_path_memory_used_percent: String(metricsPaths.memory_used_percent ?? ""),
    metrics_path_disk_used_percent: String(metricsPaths.disk_used_percent ?? ""),
    metrics_path_bandwidth_used_bytes: String(metricsPaths.bandwidth_used_bytes ?? ""),
    metrics_path_process_count: String(metricsPaths.process_count ?? ""),
    interval_seconds: c.interval_seconds ?? 300,
    enabled: c.enabled,
    vendor_id: c.vendor_id ?? "",
    configurationJson,
  };
}

function valuesToPayload(values: MonitoringCheckFormValues, check: MonitoringCheck | null) {
  const trimmedCfg = values.configurationJson.trim();
  const parsedConfiguration = trimmedCfg ? (JSON.parse(trimmedCfg) as Record<string, unknown>) : {};

  let configuration: Record<string, unknown> | null = parsedConfiguration;

  if (values.type === "server") {
    const previousCfg = ((check?.configuration ?? {}) as Record<string, unknown>) ?? {};
    const nextMetricsPaths: Record<string, string> = {};

    type MetricPathField =
      | "metrics_path_load_1m"
      | "metrics_path_load_5m"
      | "metrics_path_load_15m"
      | "metrics_path_cpu_percent"
      | "metrics_path_memory_used_percent"
      | "metrics_path_disk_used_percent"
      | "metrics_path_bandwidth_used_bytes"
      | "metrics_path_process_count";

    const pathPairs: Array<[MetricPathField, string]> = [
      ["metrics_path_load_1m", "load_1m"],
      ["metrics_path_load_5m", "load_5m"],
      ["metrics_path_load_15m", "load_15m"],
      ["metrics_path_cpu_percent", "cpu_percent"],
      ["metrics_path_memory_used_percent", "memory_used_percent"],
      ["metrics_path_disk_used_percent", "disk_used_percent"],
      ["metrics_path_bandwidth_used_bytes", "bandwidth_used_bytes"],
      ["metrics_path_process_count", "process_count"],
    ];

    for (const [field, key] of pathPairs) {
      const v = values[field].trim();
      if (v) nextMetricsPaths[key] = v;
    }

    configuration = {
      ...parsedConfiguration,
      server_provider: values.server_provider.trim() || "cpanel",
      os: values.server_os.trim(),
      hostname: values.server_hostname.trim(),
      api_base_url: values.server_api_base_url.trim(),
      api_path: values.server_api_path.trim() || undefined,
      auth_type: values.server_auth_type,
      api_username: values.server_api_username.trim() || undefined,
      api_token:
        values.server_api_token.trim() !== ""
          ? values.server_api_token.trim()
          : (previousCfg.api_token as string | undefined),
      api_password:
        values.server_api_password.trim() !== ""
          ? values.server_api_password.trim()
          : (previousCfg.api_password as string | undefined),
      verify_ssl: values.server_verify_ssl,
      metrics_paths: nextMetricsPaths,
    };
  }

  if (values.type === "tcp" || values.type === "ping") {
    configuration = {
      ...parsedConfiguration,
      port: values.tcp_port,
      timeout_ms: values.tcp_timeout_ms,
    };
  }

  if (Object.keys(configuration ?? {}).length === 0) {
    configuration = null;
  }

  const endpoint = values.endpoint.trim() || null;
  return {
    name: values.name.trim(),
    type: values.type,
    endpoint,
    configuration,
    interval_seconds: values.interval_seconds,
    enabled: values.enabled,
    vendor_id: values.vendor_id.trim() || null,
  };
}

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  check: MonitoringCheck | null;
};

export function MonitoringCheckUpsertSheet({ open, onOpenChange, check }: Props) {
  const queryClient = useQueryClient();
  const isEdit = Boolean(check);

  const vendorsQuery = useQuery({
    queryKey: queryKeys.vendors({ per_page: "100" }),
    queryFn: () => fetchVendors({ per_page: 100 }),
    enabled: open,
  });

  const form = useForm<MonitoringCheckFormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: emptyDefaults,
  });

  const { reset, handleSubmit, register, control, formState, watch, setValue } = form;
  const checkType = watch("type");
  const serverAuthType = watch("server_auth_type");

  function applyServerProviderPreset(provider: ServerProvider) {
    const preset = SERVER_PROVIDER_PRESETS[provider];
    Object.entries(preset).forEach(([key, value]) => {
      setValue(key as keyof MonitoringCheckFormValues, String(value), { shouldDirty: true });
    });
  }

  useEffect(() => {
    if (!open) return;
    reset(check ? checkToFormValues(check) : emptyDefaults);
  }, [open, check, reset]);

  const unknownType = useMemo(() => {
    if (!check) return false;
    return !(MONITORING_CHECK_TYPES as readonly string[]).includes(check.type);
  }, [check]);

  const mutation = useMutation({
    mutationFn: async (values: MonitoringCheckFormValues) => {
      const payload = valuesToPayload(values, check);
      if (check) {
        return updateMonitoringCheck(check.id, payload);
      }
      return createMonitoringCheck(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? "Check updated" : "Check created");
      void queryClient.invalidateQueries({ queryKey: ["monitoring-checks"] });
      void queryClient.invalidateQueries({ queryKey: ["monitoring-check-logs"] });
      void queryClient.invalidateQueries({ queryKey: ["monitoring-check-log-summary"] });
      onOpenChange(false);
    },
    onError: (e) => toast.error(getApiErrorMessage(e)),
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex h-full w-full max-w-lg flex-col border-l p-0 sm:max-w-xl">
        <SheetHeader className="shrink-0 border-b border-border/60 px-4 pb-3 pt-2 pr-10">
          <SheetTitle>{isEdit ? "Edit monitoring check" : "Add monitoring check"}</SheetTitle>
        </SheetHeader>
        <ScrollArea className="min-h-0 flex-1">
          <form
            id="monitoring-check-upsert-form"
            className="flex flex-col gap-4 p-4"
            onSubmit={handleSubmit((v) => mutation.mutate(v))}
            noValidate
          >
            <div className="space-y-2">
              <Label htmlFor="mc-name">Name</Label>
              <Input id="mc-name" placeholder="Homepage" autoComplete="off" {...register("name")} />
              {formState.errors.name ? (
                <p className="text-xs text-destructive">{formState.errors.name.message}</p>
              ) : null}
            </div>

            <div className="space-y-2">
              <Label htmlFor="mc-type">Check type</Label>
              <select id="mc-type" className={selectClass} {...register("type")}>
                {unknownType && check ? (
                  <option value={check.type}>
                    {check.type} (from API)
                  </option>
                ) : null}
                {MONITORING_CHECK_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ))}
              </select>
              <p className="text-xs text-muted-foreground">
                Uptime, HTTP(S), SSL, TLS, domain, and WHOIS checks require an endpoint (URL or host). TCP, ping, DNS,
                custom, and server may omit it depending on your configuration.
              </p>
            </div>

            <div className="space-y-2">
              <Label htmlFor="mc-endpoint">Endpoint</Label>
              <Input
                id="mc-endpoint"
                placeholder={
                  checkType === "server"
                    ? "https://server-host:2083"
                    : checkType === "tcp" || checkType === "ping"
                      ? "103.235.90.147"
                      : "https://example.com"
                }
                autoComplete="off"
                {...register("endpoint")}
              />
              {formState.errors.endpoint ? (
                <p className="text-xs text-destructive">{formState.errors.endpoint.message}</p>
              ) : MONITORING_CHECK_TYPES_REQUIRING_ENDPOINT.has(checkType) ? (
                <p className="text-xs text-muted-foreground">Required for type &quot;{checkType}&quot;.</p>
              ) : checkType === "tcp" || checkType === "ping" ? (
                <p className="text-xs text-muted-foreground">Required for TCP checks. Enter IP or domain only.</p>
              ) : (
                <p className="text-xs text-muted-foreground">Optional for this type.</p>
              )}
            </div>

            {checkType === "tcp" || checkType === "ping" ? (
              <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                <p className="mb-3 text-sm font-medium text-foreground">TCP socket options</p>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="mc-tcp-port">Port</Label>
                    <Input id="mc-tcp-port" type="number" min={1} max={65535} {...register("tcp_port")} />
                    {formState.errors.tcp_port ? (
                      <p className="text-xs text-destructive">{formState.errors.tcp_port.message}</p>
                    ) : (
                      <p className="text-xs text-muted-foreground">Defaults to 80.</p>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="mc-tcp-timeout">Timeout (ms)</Label>
                    <Input
                      id="mc-tcp-timeout"
                      type="number"
                      min={500}
                      max={60000}
                      step={100}
                      {...register("tcp_timeout_ms")}
                    />
                    {formState.errors.tcp_timeout_ms ? (
                      <p className="text-xs text-destructive">{formState.errors.tcp_timeout_ms.message}</p>
                    ) : (
                      <p className="text-xs text-muted-foreground">Default is 5000ms.</p>
                    )}
                  </div>
                </div>
              </div>
            ) : null}

            {checkType === "server" ? (
              <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                <p className="mb-3 text-sm font-medium text-foreground">Server details and API</p>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="mc-server-provider">Server type</Label>
                    <Controller
                      name="server_provider"
                      control={control}
                      render={({ field }) => (
                        <select
                          id="mc-server-provider"
                          className={selectClass}
                          value={field.value}
                          onChange={(e) => {
                            const provider = e.target.value as ServerProvider;
                            field.onChange(provider);
                            applyServerProviderPreset(provider);
                          }}
                        >
                          <option value="whm">WHM</option>
                          <option value="cpanel">cPanel</option>
                          <option value="plesk">Plesk</option>
                          <option value="generic">Generic API</option>
                        </select>
                      )}
                    />
                    <p className="text-xs text-muted-foreground">
                      Selecting server type auto-fills API path and metric mappings.
                    </p>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="mc-server-os">OS</Label>
                    <Input id="mc-server-os" placeholder="linux" {...register("server_os")} />
                    {formState.errors.server_os ? (
                      <p className="text-xs text-destructive">{formState.errors.server_os.message}</p>
                    ) : null}
                  </div>
                </div>

                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="mc-server-hostname">Hostname</Label>
                    <Input id="mc-server-hostname" placeholder="srv1.example.com" {...register("server_hostname")} />
                    {formState.errors.server_hostname ? (
                      <p className="text-xs text-destructive">{formState.errors.server_hostname.message}</p>
                    ) : null}
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="mc-server-api-base-url">API base URL</Label>
                    <Input
                      id="mc-server-api-base-url"
                      placeholder="https://srv1.example.com:2083"
                      {...register("server_api_base_url")}
                    />
                    {formState.errors.server_api_base_url ? (
                      <p className="text-xs text-destructive">{formState.errors.server_api_base_url.message}</p>
                    ) : null}
                  </div>
                </div>

                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="mc-server-api-path">API path</Label>
                    <Input
                      id="mc-server-api-path"
                      placeholder="execute/ResourceUsage/get_usages"
                      {...register("server_api_path")}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="mc-server-auth-type">API auth</Label>
                    <select id="mc-server-auth-type" className={selectClass} {...register("server_auth_type")}>
                      <option value="whm_token">WHM token</option>
                      <option value="cpanel_token">cPanel token</option>
                      <option value="bearer">Bearer token</option>
                      <option value="basic">Basic auth</option>
                      <option value="none">None</option>
                    </select>
                  </div>
                </div>

                {serverAuthType !== "none" ? (
                  <div className="mt-4 grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="mc-server-api-username">API username</Label>
                      <Input id="mc-server-api-username" placeholder="root" {...register("server_api_username")} />
                      {formState.errors.server_api_username ? (
                        <p className="text-xs text-destructive">{formState.errors.server_api_username.message}</p>
                      ) : null}
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="mc-server-api-token">
                        {serverAuthType === "basic" ? "API password" : "API token"}
                      </Label>
                      <Input
                        id="mc-server-api-token"
                        type="password"
                        placeholder={isEdit ? "Leave blank to keep existing secret" : "Paste secret"}
                        {...register(serverAuthType === "basic" ? "server_api_password" : "server_api_token")}
                      />
                    </div>
                  </div>
                ) : null}

                <div className="mt-4 flex items-center gap-2">
                  <Controller
                    name="server_verify_ssl"
                    control={control}
                    render={({ field }) => (
                      <input
                        type="checkbox"
                        id="mc-server-verify-ssl"
                        className="size-4 rounded border-input"
                        checked={field.value}
                        onChange={(e) => field.onChange(e.target.checked)}
                      />
                    )}
                  />
                  <Label htmlFor="mc-server-verify-ssl" className="font-normal">
                    Verify SSL certificate for API calls
                  </Label>
                </div>

                <div className="mt-4">
                  <p className="mb-2 text-xs text-muted-foreground">
                    Optional metric paths (dot notation) if your API response uses different keys.
                  </p>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <Input placeholder="load path (e.g. data.one)" {...register("metrics_path_load_1m")} />
                    <Input placeholder="cpu path" {...register("metrics_path_cpu_percent")} />
                    <Input placeholder="memory path" {...register("metrics_path_memory_used_percent")} />
                    <Input placeholder="disk path" {...register("metrics_path_disk_used_percent")} />
                    <Input placeholder="bandwidth path" {...register("metrics_path_bandwidth_used_bytes")} />
                    <Input placeholder="process count path" {...register("metrics_path_process_count")} />
                  </div>
                </div>
              </div>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="mc-interval">Interval (seconds)</Label>
                <Input id="mc-interval" type="number" min={60} max={86400} step={60} {...register("interval_seconds")} />
                {formState.errors.interval_seconds ? (
                  <p className="text-xs text-destructive">{formState.errors.interval_seconds.message}</p>
                ) : (
                  <p className="text-xs text-muted-foreground">60–86400 (API default minimum is 60).</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="mc-vendor">Vendor (optional)</Label>
                {vendorsQuery.isLoading ? (
                  <Skeleton className="h-9 w-full" />
                ) : (
                  <select id="mc-vendor" className={selectClass} {...register("vendor_id")}>
                    <option value="">None</option>
                    {vendorsQuery.data?.items.map((v) => (
                      <option key={v.id} value={v.id}>
                        {v.name}
                      </option>
                    ))}
                  </select>
                )}
              </div>
            </div>

            <div className="flex items-center gap-2">
              <Controller
                name="enabled"
                control={control}
                render={({ field }) => (
                  <input
                    type="checkbox"
                    id="mc-enabled"
                    className="size-4 rounded border-input"
                    checked={field.value}
                    onChange={(e) => field.onChange(e.target.checked)}
                  />
                )}
              />
              <Label htmlFor="mc-enabled" className="font-normal">
                Enabled (scheduler will run this check)
              </Label>
            </div>

            <div className="space-y-2">
              <Label htmlFor="mc-config">Configuration (JSON)</Label>
              <p className="text-xs leading-relaxed text-muted-foreground">
                Per-check probe options (timeouts, HTTP method, expected status, SSL port/verify, retries, etc.). The{" "}
                <strong className="font-medium text-foreground">endpoint</strong> field is still the URL or host; this JSON
                only tweaks how the run behaves. See{" "}
                <Link
                  href="/docs/configure#monitoring-configuration"
                  className="font-medium text-primary underline-offset-4 hover:underline"
                >
                  Docs → Configure → §5 monitoring checks
                </Link>{" "}
                for field lists and examples, and{" "}
                <Link href="/docs/monitoring" className="font-medium text-primary underline-offset-4 hover:underline">
                  Site monitoring
                </Link>{" "}
                for how runs are queued and logged. Empty <code className="rounded bg-muted px-1 text-[11px]">{"{}"}</code> uses
                built-in defaults.
              </p>
              <textarea
                id="mc-config"
                className={textareaClass}
                rows={6}
                placeholder='{ "method": "HEAD", "expected_status": 200 }'
                {...register("configurationJson")}
              />
              {formState.errors.configurationJson ? (
                <p className="text-xs text-destructive">{formState.errors.configurationJson.message}</p>
              ) : null}
            </div>
          </form>

          <div className="flex flex-wrap justify-end gap-2 border-t border-border/60 p-4 pt-4">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" form="monitoring-check-upsert-form" disabled={mutation.isPending || formState.isSubmitting}>
              {mutation.isPending ? "Saving…" : isEdit ? "Save changes" : "Create check"}
            </Button>
          </div>
        </ScrollArea>
      </SheetContent>
    </Sheet>
  );
}
