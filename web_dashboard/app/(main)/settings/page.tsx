"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Activity,
  Bell,
  BookText,
  Building2,
  ClipboardCopy,
  FileSpreadsheet,
  Inbox,
  LayoutGrid,
  Mail,
  User,
  UserPlus,
} from "lucide-react";
import { toast } from "sonner";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { getApiErrorMessage } from "@/lib/api/errors";
import { fetchOrganizations } from "@/lib/api/organizations";
import {
  fetchOrganizationSmtpSettings,
  sendOrganizationSmtpTestEmail,
  updateOrganizationSmtpSettings,
} from "@/lib/api/organization-smtp";
import {
  fetchMainSmtpSettings,
  sendMainSmtpTestEmail,
  updateMainSmtpSettings,
} from "@/lib/api/main-smtp-settings";
import {
  fetchManagementEmailSettings,
  updateManagementEmailSettings,
} from "@/lib/api/management-email-settings";
import { queryKeys } from "@/lib/api/query-keys";
import { useAuthStore, useOrganizations } from "@/stores/auth-store";
import { publicApiBaseUrl } from "@/lib/api/public-url";
import { LIST_PAGE_SIZE_OPTIONS, useDashboardSettings, type ListPageSize } from "@/stores/dashboard-settings-store";
import { cn } from "@/lib/utils";

const selectClass = cn(
  "flex h-9 w-full max-w-[220px] rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-sm",
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
);

const inputClass = cn(
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm text-foreground shadow-sm",
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
);

async function copyToClipboard(value: string, description: string) {
  try {
    await navigator.clipboard.writeText(value);
    toast.success(`${description} copied`);
  } catch {
    toast.error("Could not copy to clipboard");
  }
}

export default function SettingsPage() {
  const queryClient = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const organizationId = useAuthStore((s) => s.organizationId);
  const organizations = useOrganizations();
  const isAdmin = Boolean(user?.is_admin);

  const [smtpHost, setSmtpHost] = useState("");
  const [smtpPort, setSmtpPort] = useState("587");
  const [smtpUsername, setSmtpUsername] = useState("");
  const [smtpPassword, setSmtpPassword] = useState("");
  const [smtpEncryption, setSmtpEncryption] = useState<"tls" | "ssl" | "none">("tls");
  const [smtpFromAddress, setSmtpFromAddress] = useState("");
  const [smtpFromName, setSmtpFromName] = useState("VendorPulse");
  const [smtpReplyToAddress, setSmtpReplyToAddress] = useState("");
  const [smtpReplyToName, setSmtpReplyToName] = useState("");
  const [smtpTestTo, setSmtpTestTo] = useState("");
  const [notifyOrganizationCreated, setNotifyOrganizationCreated] = useState(true);
  const [notifyUserCreated, setNotifyUserCreated] = useState(true);
  const [mainSmtpHost, setMainSmtpHost] = useState("");
  const [mainSmtpPort, setMainSmtpPort] = useState("587");
  const [mainSmtpUsername, setMainSmtpUsername] = useState("");
  const [mainSmtpPassword, setMainSmtpPassword] = useState("");
  const [mainSmtpEncryption, setMainSmtpEncryption] = useState<"tls" | "ssl" | "none">("tls");
  const [mainSmtpFromAddress, setMainSmtpFromAddress] = useState("");
  const [mainSmtpFromName, setMainSmtpFromName] = useState("VendorPulse");
  const [mainSmtpReplyToAddress, setMainSmtpReplyToAddress] = useState("");
  const [mainSmtpReplyToName, setMainSmtpReplyToName] = useState("");
  const [mainSmtpTestTo, setMainSmtpTestTo] = useState("");

  const vendorsPerPage = useDashboardSettings((s) => s.vendorsPerPage);
  const invoicesPerPage = useDashboardSettings((s) => s.invoicesPerPage);
  const monitoringPerPage = useDashboardSettings((s) => s.monitoringPerPage);
  const notificationsPerPage = useDashboardSettings((s) => s.notificationsPerPage);
  const setVendorsPerPage = useDashboardSettings((s) => s.setVendorsPerPage);
  const setInvoicesPerPage = useDashboardSettings((s) => s.setInvoicesPerPage);
  const setMonitoringPerPage = useDashboardSettings((s) => s.setMonitoringPerPage);
  const setNotificationsPerPage = useDashboardSettings((s) => s.setNotificationsPerPage);

  const activeOrg = useMemo(
    () => organizations.find((o) => o.id === organizationId) ?? null,
    [organizations, organizationId],
  );
  const canManageSmtp = Boolean(
    isAdmin || (organizationId && activeOrg && (activeOrg.role === "owner" || activeOrg.role === "admin")),
  );

  const orgSettingsJson = useMemo(() => {
    const s = activeOrg?.settings;
    if (s === null || s === undefined) return null;
    try {
      return JSON.stringify(s, null, 2);
    } catch {
      return String(s);
    }
  }, [activeOrg?.settings]);

  const hasOrgSettings = orgSettingsJson !== null && orgSettingsJson !== "{}" && orgSettingsJson !== "null";

  useQuery({
    queryKey: queryKeys.organizations(),
    queryFn: async () => {
      const response = await fetchOrganizations();
      return response.data;
    },
    enabled: isAdmin,
  });

  const smtpSettingsQuery = useQuery({
    queryKey: queryKeys.organizationSmtpSettings,
    queryFn: fetchOrganizationSmtpSettings,
    enabled: canManageSmtp && Boolean(organizationId),
    staleTime: 60_000,
  });

  useEffect(() => {
    const smtp = smtpSettingsQuery.data;
    if (!smtp) return;

    setSmtpHost(smtp.host ?? "");
    setSmtpPort(String(smtp.port ?? 587));
    setSmtpUsername(smtp.username ?? "");
    setSmtpPassword("");
    setSmtpEncryption(smtp.encryption ?? "none");
    setSmtpFromAddress(smtp.from_address ?? "");
    setSmtpFromName(smtp.from_name ?? "VendorPulse");
    setSmtpReplyToAddress(smtp.reply_to_address ?? "");
    setSmtpReplyToName(smtp.reply_to_name ?? "");
    setSmtpTestTo(user?.email ?? "");
  }, [smtpSettingsQuery.data, user?.email]);

  const saveSmtpMutation = useMutation({
    mutationFn: updateOrganizationSmtpSettings,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.organizationSmtpSettings });
      setSmtpPassword("");
      toast.success("SMTP settings saved");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const testSmtpMutation = useMutation({
    mutationFn: sendOrganizationSmtpTestEmail,
    onSuccess: () => {
      toast.success("Test email sent");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const managementEmailSettingsQuery = useQuery({
    queryKey: queryKeys.managementEmailSettings,
    queryFn: fetchManagementEmailSettings,
    enabled: isAdmin,
    staleTime: 60_000,
  });

  useEffect(() => {
    if (!managementEmailSettingsQuery.data) return;

    setNotifyOrganizationCreated(Boolean(managementEmailSettingsQuery.data.notify_organization_created));
    setNotifyUserCreated(Boolean(managementEmailSettingsQuery.data.notify_user_created));
  }, [managementEmailSettingsQuery.data]);

  const saveManagementEmailSettingsMutation = useMutation({
    mutationFn: updateManagementEmailSettings,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.managementEmailSettings });
      toast.success("Management email settings saved");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const handleSaveManagementEmailSettings = () => {
    saveManagementEmailSettingsMutation.mutate({
      notify_organization_created: notifyOrganizationCreated,
      notify_user_created: notifyUserCreated,
    });
  };

  const mainSmtpSettingsQuery = useQuery({
    queryKey: queryKeys.mainSmtpSettings,
    queryFn: fetchMainSmtpSettings,
    enabled: isAdmin,
    staleTime: 60_000,
  });

  useEffect(() => {
    const smtp = mainSmtpSettingsQuery.data;
    if (!smtp) return;

    setMainSmtpHost(smtp.host ?? "");
    setMainSmtpPort(String(smtp.port ?? 587));
    setMainSmtpUsername(smtp.username ?? "");
    setMainSmtpPassword("");
    setMainSmtpEncryption(smtp.encryption ?? "none");
    setMainSmtpFromAddress(smtp.from_address ?? "");
    setMainSmtpFromName(smtp.from_name ?? "VendorPulse");
    setMainSmtpReplyToAddress(smtp.reply_to_address ?? "");
    setMainSmtpReplyToName(smtp.reply_to_name ?? "");
    setMainSmtpTestTo(user?.email ?? "");
  }, [mainSmtpSettingsQuery.data, user?.email]);

  const saveMainSmtpMutation = useMutation({
    mutationFn: updateMainSmtpSettings,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.mainSmtpSettings });
      setMainSmtpPassword("");
      toast.success("Main SMTP settings saved");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const testMainSmtpMutation = useMutation({
    mutationFn: sendMainSmtpTestEmail,
    onSuccess: () => {
      toast.success("Main SMTP test email sent");
    },
    onError: (error) => {
      toast.error(getApiErrorMessage(error));
    },
  });

  const handleSaveMainSmtp = () => {
    if (!mainSmtpHost.trim() || !mainSmtpFromAddress.trim() || !mainSmtpFromName.trim()) {
      toast.error("Host, From address, and From name are required");
      return;
    }

    const portNumber = Number(mainSmtpPort);
    if (!Number.isInteger(portNumber) || portNumber < 1 || portNumber > 65535) {
      toast.error("SMTP port must be between 1 and 65535");
      return;
    }

    saveMainSmtpMutation.mutate({
      host: mainSmtpHost.trim(),
      port: portNumber,
      username: mainSmtpUsername.trim() || undefined,
      password: mainSmtpPassword || undefined,
      encryption: mainSmtpEncryption,
      from_address: mainSmtpFromAddress.trim(),
      from_name: mainSmtpFromName.trim(),
      reply_to_address: mainSmtpReplyToAddress.trim() || undefined,
      reply_to_name: mainSmtpReplyToName.trim() || undefined,
    });
  };

  const handleSendMainSmtpTest = () => {
    if (!mainSmtpTestTo.trim()) {
      toast.error("Test recipient email is required");
      return;
    }

    testMainSmtpMutation.mutate({
      to: mainSmtpTestTo.trim(),
      subject: "VendorPulse main SMTP test",
      message: "This is a test email from VendorPulse main SMTP configuration.",
    });
  };

  const handleSaveSmtp = () => {
    if (!organizationId) {
      toast.error("Select an organization first");
      return;
    }

    if (!smtpHost.trim() || !smtpFromAddress.trim() || !smtpFromName.trim()) {
      toast.error("Host, From address, and From name are required");
      return;
    }

    const portNumber = Number(smtpPort);
    if (!Number.isInteger(portNumber) || portNumber < 1 || portNumber > 65535) {
      toast.error("SMTP port must be between 1 and 65535");
      return;
    }

    saveSmtpMutation.mutate({
      host: smtpHost.trim(),
      port: portNumber,
      username: smtpUsername.trim() || undefined,
      password: smtpPassword || undefined,
      encryption: smtpEncryption,
      from_address: smtpFromAddress.trim(),
      from_name: smtpFromName.trim(),
      reply_to_address: smtpReplyToAddress.trim() || undefined,
      reply_to_name: smtpReplyToName.trim() || undefined,
    });
  };

  const handleSendSmtpTest = () => {
    if (!organizationId) {
      toast.error("Select an organization first");
      return;
    }
    if (!smtpTestTo.trim()) {
      toast.error("Test recipient email is required");
      return;
    }

    testSmtpMutation.mutate({
      to: smtpTestTo.trim(),
      subject: "VendorPulse SMTP test",
      message: "This is a test email from VendorPulse SMTP configuration.",
    });
  };

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Settings</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Workspace context, dashboard preferences (stored in this browser), and links to where work happens.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Mail className="h-4 w-4" />
            Email mailboxes
          </CardTitle>
          <CardDescription>
            Organization-level IMAP or Gmail API credentials for inbound email monitoring (stored in{" "}
            <code className="rounded bg-muted px-1 text-xs">email_mailboxes</code>). Configure in the dashboard; the API
            never returns secrets.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button variant="secondary" size="sm" asChild>
            <Link href="/settings/mailboxes" className="gap-1.5">
              <Inbox className="h-3.5 w-3.5" />
              Manage mailboxes
            </Link>
          </Button>
        </CardContent>
      </Card>

      {isAdmin ? (
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Mail className="h-4 w-4" />
              Main VendorPulse SMTP
            </CardTitle>
            <CardDescription>
              Configure the global SMTP channel used for system-level emails (admin only).
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {mainSmtpSettingsQuery.isError ? (
              <p className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                Main SMTP settings are temporarily unavailable on this environment. Run backend migrations and deploy latest API code.
              </p>
            ) : (
              <>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-host">SMTP host</Label>
                  <input id="main-smtp-host" className={inputClass} value={mainSmtpHost} onChange={(e) => setMainSmtpHost(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-port">SMTP port</Label>
                  <input id="main-smtp-port" className={inputClass} value={mainSmtpPort} onChange={(e) => setMainSmtpPort(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-username">Username</Label>
                  <input id="main-smtp-username" className={inputClass} value={mainSmtpUsername} onChange={(e) => setMainSmtpUsername(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-password">Password (leave blank to keep existing)</Label>
                  <input id="main-smtp-password" type="password" className={inputClass} value={mainSmtpPassword} onChange={(e) => setMainSmtpPassword(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-encryption">Encryption</Label>
                  <select
                    id="main-smtp-encryption"
                    className={selectClass}
                    value={mainSmtpEncryption}
                    onChange={(e) => setMainSmtpEncryption(e.target.value as "tls" | "ssl" | "none")}
                  >
                    <option value="tls">tls</option>
                    <option value="ssl">ssl</option>
                    <option value="none">none</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-from-address">From address</Label>
                  <input id="main-smtp-from-address" className={inputClass} value={mainSmtpFromAddress} onChange={(e) => setMainSmtpFromAddress(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-from-name">From name</Label>
                  <input id="main-smtp-from-name" className={inputClass} value={mainSmtpFromName} onChange={(e) => setMainSmtpFromName(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-reply-to-address">Reply-to address (optional)</Label>
                  <input id="main-smtp-reply-to-address" className={inputClass} value={mainSmtpReplyToAddress} onChange={(e) => setMainSmtpReplyToAddress(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-reply-to-name">Reply-to name (optional)</Label>
                  <input id="main-smtp-reply-to-name" className={inputClass} value={mainSmtpReplyToName} onChange={(e) => setMainSmtpReplyToName(e.target.value)} />
                </div>
              </div>

              <div className="flex flex-wrap gap-2">
                <Button type="button" onClick={handleSaveMainSmtp} disabled={saveMainSmtpMutation.isPending}>
                  {saveMainSmtpMutation.isPending ? "Saving..." : "Save main SMTP settings"}
                </Button>
              </div>

              <Separator />

              <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                <div className="space-y-2">
                  <Label htmlFor="main-smtp-test-to">Send test email to</Label>
                  <input id="main-smtp-test-to" className={inputClass} value={mainSmtpTestTo} onChange={(e) => setMainSmtpTestTo(e.target.value)} />
                </div>
                <div className="flex items-end">
                  <Button type="button" variant="outline" onClick={handleSendMainSmtpTest} disabled={testMainSmtpMutation.isPending}>
                    {testMainSmtpMutation.isPending ? "Sending..." : "Send main SMTP test"}
                  </Button>
                </div>
              </div>
              </>
            )}
          </CardContent>
        </Card>
      ) : null}

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Mail className="h-4 w-4" />
            Outbound SMTP
          </CardTitle>
          <CardDescription>
            Configure organization SMTP for sending emails. Allowed for global admins and selected organization owners/admins.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {!canManageSmtp ? (
            <p className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
              You need to be a global admin or an owner/admin of the selected organization to configure SMTP.
            </p>
          ) : !organizationId ? (
            <p className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
              Select an organization first to configure SMTP.
            </p>
          ) : smtpSettingsQuery.isError ? (
            <p className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
              Organization SMTP settings are temporarily unavailable on this environment. Run backend migrations and deploy latest API code.
            </p>
          ) : (
            <>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="smtp-host">SMTP host</Label>
                  <input id="smtp-host" className={inputClass} value={smtpHost} onChange={(e) => setSmtpHost(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-port">SMTP port</Label>
                  <input id="smtp-port" className={inputClass} value={smtpPort} onChange={(e) => setSmtpPort(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-username">Username</Label>
                  <input id="smtp-username" className={inputClass} value={smtpUsername} onChange={(e) => setSmtpUsername(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-password">Password (leave blank to keep existing)</Label>
                  <input id="smtp-password" type="password" className={inputClass} value={smtpPassword} onChange={(e) => setSmtpPassword(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-encryption">Encryption</Label>
                  <select
                    id="smtp-encryption"
                    className={selectClass}
                    value={smtpEncryption}
                    onChange={(e) => setSmtpEncryption(e.target.value as "tls" | "ssl" | "none")}
                  >
                    <option value="tls">tls</option>
                    <option value="ssl">ssl</option>
                    <option value="none">none</option>
                  </select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-from-address">From address</Label>
                  <input id="smtp-from-address" className={inputClass} value={smtpFromAddress} onChange={(e) => setSmtpFromAddress(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-from-name">From name</Label>
                  <input id="smtp-from-name" className={inputClass} value={smtpFromName} onChange={(e) => setSmtpFromName(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-reply-to-address">Reply-to address (optional)</Label>
                  <input id="smtp-reply-to-address" className={inputClass} value={smtpReplyToAddress} onChange={(e) => setSmtpReplyToAddress(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="smtp-reply-to-name">Reply-to name (optional)</Label>
                  <input id="smtp-reply-to-name" className={inputClass} value={smtpReplyToName} onChange={(e) => setSmtpReplyToName(e.target.value)} />
                </div>
              </div>

              <div className="flex flex-wrap gap-2">
                <Button type="button" onClick={handleSaveSmtp} disabled={saveSmtpMutation.isPending}>
                  {saveSmtpMutation.isPending ? "Saving..." : "Save SMTP settings"}
                </Button>
              </div>

              <Separator />

              <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                <div className="space-y-2">
                  <Label htmlFor="smtp-test-to">Send test email to</Label>
                  <input id="smtp-test-to" className={inputClass} value={smtpTestTo} onChange={(e) => setSmtpTestTo(e.target.value)} />
                </div>
                <div className="flex items-end">
                  <Button type="button" variant="outline" onClick={handleSendSmtpTest} disabled={testSmtpMutation.isPending}>
                    {testSmtpMutation.isPending ? "Sending..." : "Send test"}
                  </Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {isAdmin ? (
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <Bell className="h-4 w-4" />
              Management activity emails
            </CardTitle>
            <CardDescription>
              Control main VendorPulse emails sent for management actions across all organizations.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {managementEmailSettingsQuery.isError ? (
              <p className="rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                Management email settings are temporarily unavailable on this environment. Run backend migrations and deploy latest API code.
              </p>
            ) : (
              <>
              <label className="flex items-center justify-between gap-3 rounded-md border border-border/60 p-3">
                <span className="text-sm">Send email when organization is created</span>
                <input
                  type="checkbox"
                  checked={notifyOrganizationCreated}
                  onChange={(e) => setNotifyOrganizationCreated(e.target.checked)}
                  className="h-4 w-4"
                />
              </label>
              <label className="flex items-center justify-between gap-3 rounded-md border border-border/60 p-3">
                <span className="text-sm">Send email when user is created</span>
                <input
                  type="checkbox"
                  checked={notifyUserCreated}
                  onChange={(e) => setNotifyUserCreated(e.target.checked)}
                  className="h-4 w-4"
                />
              </label>
              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  onClick={handleSaveManagementEmailSettings}
                  disabled={saveManagementEmailSettingsMutation.isPending || managementEmailSettingsQuery.isLoading}
                >
                  {saveManagementEmailSettingsMutation.isPending ? "Saving..." : "Save management email settings"}
                </Button>
              </div>
              </>
            )}
          </CardContent>
        </Card>
      ) : null}

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <LayoutGrid className="h-4 w-4" />
            Dashboard lists
          </CardTitle>
          <CardDescription>
            Rows per page for main tables. Saved locally in <code className="rounded bg-muted px-1">localStorage</code>{" "}
            (not sent to the API).
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <PerPageField
            id="set-vendors-per-page"
            label="Vendors"
            value={vendorsPerPage}
            onChange={(v) => setVendorsPerPage(v)}
          />
          <PerPageField
            id="set-invoices-per-page"
            label="Invoices"
            value={invoicesPerPage}
            onChange={(v) => setInvoicesPerPage(v)}
          />
          <PerPageField
            id="set-monitoring-per-page"
            label="Monitoring"
            value={monitoringPerPage}
            onChange={(v) => setMonitoringPerPage(v)}
          />
          <PerPageField
            id="set-notifications-per-page"
            label="Notifications"
            value={notificationsPerPage}
            onChange={(v) => setNotificationsPerPage(v)}
          />
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Building2 className="h-4 w-4" />
            Organization
          </CardTitle>
          <CardDescription>
            Active org from <code className="rounded bg-muted px-1">X-Organization-Id</code> (header switcher). Server
            may attach JSON <code className="rounded bg-muted px-1">settings</code> on the org — shown read-only here.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4 text-sm">
          <div>
            <p className="text-muted-foreground">Active</p>
            <p className="font-medium">{activeOrg?.name ?? "—"}</p>
            {activeOrg?.slug ? (
              <p className="text-xs text-muted-foreground">
                Slug: <span className="font-mono">{activeOrg.slug}</span>
              </p>
            ) : null}
          </div>

          <div className="flex flex-wrap gap-2">
            {organizationId ? (
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="gap-1.5"
                onClick={() => void copyToClipboard(organizationId, "Organization ID")}
              >
                <ClipboardCopy className="h-3.5 w-3.5" />
                Copy organization ID
              </Button>
            ) : null}
            {user?.id ? (
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="gap-1.5"
                onClick={() => void copyToClipboard(user.id, "User ID")}
              >
                <ClipboardCopy className="h-3.5 w-3.5" />
                Copy user ID
              </Button>
            ) : null}
          </div>

          <div>
            <p className="mb-1 text-muted-foreground">Organization settings (API)</p>
            {hasOrgSettings ? (
              <pre className="max-h-48 overflow-auto rounded-md border border-border/60 bg-muted/40 p-3 font-mono text-xs">
                {orgSettingsJson}
              </pre>
            ) : (
              <p className="text-xs text-muted-foreground">None returned for this organization.</p>
            )}
          </div>

          <Separator />
          <div>
            <p className="text-muted-foreground">Memberships</p>
            <ul className="mt-1 list-inside list-disc space-y-1 text-muted-foreground">
              {organizations.length === 0 ? (
                <li>No organizations on this account.</li>
              ) : (
                organizations.map((o) => (
                  <li key={o.id}>
                    <span className={o.id === organizationId ? "font-medium text-foreground" : ""}>{o.name}</span>
                    {o.role ? <span className="ml-1 text-xs uppercase text-muted-foreground">({o.role})</span> : null}
                    {o.id === organizationId ? (
                      <span className="ml-1 text-xs text-primary">(active)</span>
                    ) : null}
                  </li>
                ))
              )}
            </ul>
          </div>
        </CardContent>
      </Card>

      {isAdmin ? (
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-base">
              <UserPlus className="h-4 w-4" />
              Administration
            </CardTitle>
            <CardDescription>
              Manage organizations, users, and platform access.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <Button variant="secondary" size="sm" asChild>
              <Link href="/admin" className="gap-1.5">
                <UserPlus className="h-3.5 w-3.5" />
                Open administration
              </Link>
            </Button>
          </CardContent>
        </Card>
      ) : null}

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <User className="h-4 w-4" />
            Account
          </CardTitle>
          <CardDescription>Signed-in user from Sanctum <code className="rounded bg-muted px-1">/auth/me</code>.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3 text-sm">
          <div>
            <p className="text-muted-foreground">Name</p>
            <p className="font-medium">{user?.name ?? "—"}</p>
          </div>
          <div className="flex items-start gap-2">
            <Mail className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <div>
              <p className="text-muted-foreground">Email</p>
              <p className="font-medium">{user?.email ?? "—"}</p>
            </div>
          </div>
          {user?.timezone ? (
            <div>
              <p className="text-muted-foreground">Timezone</p>
              <p className="font-medium">{user.timezone}</p>
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="text-base">Shortcuts</CardTitle>
          <CardDescription>Primary screens for day-to-day work.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-2">
          <Button variant="secondary" size="sm" asChild>
            <Link href="/dashboard" className="gap-1.5">
              <LayoutGrid className="h-3.5 w-3.5" />
              Dashboard
            </Link>
          </Button>
          <Button variant="secondary" size="sm" asChild>
            <Link href="/vendors" className="gap-1.5">
              <Building2 className="h-3.5 w-3.5" />
              Vendors
            </Link>
          </Button>
          <Button variant="secondary" size="sm" asChild>
            <Link href="/invoices" className="gap-1.5">
              <FileSpreadsheet className="h-3.5 w-3.5" />
              Invoices
            </Link>
          </Button>
          <Button variant="secondary" size="sm" asChild>
            <Link href="/monitoring" className="gap-1.5">
              <Activity className="h-3.5 w-3.5" />
              Monitoring
            </Link>
          </Button>
          <Button variant="secondary" size="sm" asChild>
            <Link href="/notifications" className="gap-1.5">
              <Bell className="h-3.5 w-3.5" />
              Notifications
            </Link>
          </Button>
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Inbox className="h-4 w-4" />
            Vendor inbound email
          </CardTitle>
          <CardDescription>
            There is no global subject tag. Each vendor has its own allowlisted addresses and optional domain rule.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3 text-sm text-muted-foreground">
          <ul className="list-inside list-disc space-y-2">
            <li>
              <strong className="text-foreground">Where mail is read</strong> —{" "}
              <Link href="/settings/mailboxes" className="font-medium text-primary underline-offset-4 hover:underline">
                Settings → Email mailboxes
              </Link>{" "}
              holds IMAP/Gmail credentials. If each vendor has a different organizational inbox, add{" "}
              <strong className="text-foreground">one mailbox per inbox</strong>; all enabled mailboxes are polled.
            </li>
            <li>
              <strong className="text-foreground">Exact addresses</strong> — Add From / To / Cc addresses under{" "}
              <Link href="/vendors" className="font-medium text-primary underline-offset-4 hover:underline">
                Vendors → Edit vendor → Incoming mail addresses
              </Link>{" "}
              (or when adding a vendor). That list decides which vendor a fetched message belongs to.
            </li>
            <li>
              <strong className="text-foreground">Domain match</strong> — Off by default; enable per vendor only if you
              accept the risk of same-domain mail being attributed to that vendor.
            </li>
          </ul>
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="text-base">API</CardTitle>
          <CardDescription>Dashboard base URL for this build.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3 text-sm">
          <div>
            <p className="text-muted-foreground">Base URL</p>
            <p className="break-all font-mono text-xs">{publicApiBaseUrl}</p>
          </div>
          <Button variant="outline" size="sm" className="gap-1.5" asChild>
            <Link href="/docs">
              <BookText className="h-3.5 w-3.5" />
              API documentation
            </Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}

function PerPageField({
  id,
  label,
  value,
  onChange,
}: {
  id: string;
  label: string;
  value: ListPageSize;
  onChange: (v: ListPageSize) => void;
}) {
  return (
    <div className="space-y-2">
      <Label htmlFor={id}>{label}</Label>
      <select
        id={id}
        className={selectClass}
        value={value}
        onChange={(e) => onChange(Number(e.target.value) as ListPageSize)}
      >
        {LIST_PAGE_SIZE_OPTIONS.map((n) => (
          <option key={n} value={n}>
            {n} per page
          </option>
        ))}
      </select>
    </div>
  );
}
