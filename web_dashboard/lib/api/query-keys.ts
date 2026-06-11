export const queryKeys = {
  me: ["auth", "me"] as const,
  /** Prefix — invalidates every organizations list query. */
  organizationsAll: ["organizations"] as const,
  organizations: (params?: { include_trashed?: boolean }) => ["organizations", params ?? {}] as const,
  organization: (id: string) => ["organization", id] as const,
  organizationUsers: (params?: { include_trashed?: boolean }) =>
    ["organization-users", params ?? {}] as const,
  organizationUser: (id: string) => ["organization-user", id] as const,
  organizationSmtpSettings: ["organization-smtp-settings"] as const,
  managementEmailSettings: ["management-email-settings"] as const,
  mainSmtpSettings: ["main-smtp-settings"] as const,
  vendors: (params?: Record<string, string>) => ["vendors", params ?? {}] as const,
  vendorEmails: (vendorId: string) => ["vendor-emails", vendorId] as const,
  invoices: (params?: Record<string, string>) => ["invoices", params ?? {}] as const,
  monitoringChecks: (params?: Record<string, string>) =>
    ["monitoring-checks", params ?? {}] as const,
  monitoringCheck: (id: string) => ["monitoring-check", id] as const,
  monitoringCheckLogs: (id: string, params?: Record<string, string>) =>
    ["monitoring-check-logs", id, params ?? {}] as const,
  monitoringCheckLogSummary: (id: string, params?: Record<string, string>) =>
    ["monitoring-check-log-summary", id, params ?? {}] as const,
  monitoringServerAnalytics: (id: string, params?: Record<string, string>) =>
    ["monitoring-server-analytics", id, params ?? {}] as const,
  experienceMonitoringTests: (params?: Record<string, string>) =>
    ["experience-monitoring-tests", params ?? {}] as const,
  experienceMonitoringTest: (id: string) => ["experience-monitoring-test", id] as const,
  experienceMonitoringRuns: (id: string, params?: Record<string, string>) =>
    ["experience-monitoring-runs", id, params ?? {}] as const,
  experienceMonitoringMetrics: (id: string, params?: Record<string, string>) =>
    ["experience-monitoring-metrics", id, params ?? {}] as const,
  experienceMonitoringReport: (id: string, params?: Record<string, string>) =>
    ["experience-monitoring-report", id, params ?? {}] as const,
  experienceMonitoringScreenshots: (id: string, params?: Record<string, string>) =>
    ["experience-monitoring-screenshots", id, params ?? {}] as const,
  websiteSpeedtestRuns: (params?: Record<string, string>) =>
    ["website-speedtest-runs", params ?? {}] as const,
  domainSocialAccounts: (monitoringCheckId?: string) =>
    ["domain-social-accounts", monitoringCheckId ?? "all"] as const,
  emailMailboxes: (params?: Record<string, string>) => ["email-mailboxes", params ?? {}] as const,
  emailLogs: (params?: Record<string, string>) => ["email-logs", params ?? {}] as const,
  emailLog: (id: string) => ["email-log", id] as const,
  notifications: (params?: Record<string, string>) =>
    ["notifications", params ?? {}] as const,
  dashboardTrends: (params?: Record<string, string>) =>
    ["dashboard-trends", params ?? {}] as const,
  dashboardMonitoringCreateFallbackTrends: (params?: Record<string, string>) =>
    ["dashboard-monitoring-create-fallback-trends", params ?? {}] as const,
  onPageSeoAudit: (targetUrl: string) => ["on-page-seo-audit", targetUrl] as const,
};
