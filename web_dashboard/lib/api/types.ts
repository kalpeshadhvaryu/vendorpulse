import type { ExperienceMonitoringBrowserType } from "@/lib/experience-monitoring/browsers";

export type Organization = {
  id: string;
  name: string;
  slug: string;
  phone_country_code?: string | null;
  phone_number?: string | null;
  role?: string;
  settings: Record<string, unknown> | null;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string | null;
};

export type User = {
  id: string;
  name: string;
  email: string;
  timezone: string | null;
  is_admin?: boolean;
  default_organization_id: string | null;
  membership_role?: string | null;
  organizations?: Organization[];
  default_organization?: Organization | null;
  created_at?: string;
  deleted_at?: string | null;
};

export type OrganizationDetail = {
  organization: Organization;
  members: User[];
};

export type Vendor = {
  id: string;
  company_id: string;
  name: string;
  vendor_type: string;
  billing_email: string | null;
  support_email: string | null;
  website: string | null;
  currency: string;
  expected_amount: string | null;
  billing_cycle: string;
  renewal_date: string | null;
  auto_detect_invoices: boolean;
  auto_fetch_email: boolean;
  match_inbound_from_website_domain: boolean;
  monitoring_enabled: boolean;
  notes: string | null;
  status: string;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string | null;
};

export type VendorEmail = {
  id: string;
  organization_id: string;
  vendor_id: string;
  email: string;
  label: string | null;
  purpose: string;
  is_monitored: boolean;
  last_checked_at: string | null;
  last_check_status: string | null;
  metadata: Record<string, unknown> | null;
  created_at?: string;
  updated_at?: string;
};

export type InvoiceEmailSource = {
  email_log_id: string;
  auto_generated: boolean;
  source: string | null;
  subject?: string | null;
  from_email?: string | null;
  received_at?: string | null;
};

export type Invoice = {
  id: string;
  organization_id: string;
  vendor_id: string | null;
  number: string;
  status: string;
  currency: string;
  amount_cents: number;
  tax_cents: number;
  issued_on: string | null;
  due_on: string | null;
  paid_at: string | null;
  description: string | null;
  metadata: Record<string, unknown> | null;
  email_source: InvoiceEmailSource | null;
  created_at?: string;
  updated_at?: string;
};

export type EmailLogInvoiceOutcome = {
  kind: string;
  label: string;
  reason: string | null;
  invoice_id: string | null;
  invoice_number: string | null;
  event: string | null;
};

export type EmailLog = {
  id: string;
  organization_id: string;
  email_mailbox_id: string | null;
  mailbox_name?: string | null;
  external_message_id: string | null;
  subject: string | null;
  from_email: string | null;
  received_at: string | null;
  processing_status: string;
  vendor_id: string | null;
  vendor_name?: string | null;
  vendor_match_confidence: number | null;
  failure_reason: string | null;
  invoice_outcome: EmailLogInvoiceOutcome;
  latest_extraction?: {
    id: string;
    status: string;
    aggregate_confidence: number | null;
    candidate_invoice_number?: string | null;
    candidate_event?: string | null;
  } | null;
  created_at?: string;
  updated_at?: string;
};

export type EmailLogDetail = EmailLog & {
  to_recipients: string[];
  cc_recipients: string[];
  body_text_preview: string | null;
  processing_meta: Record<string, unknown>;
  extractions?: Array<{
    id: string;
    status: string;
    aggregate_confidence: number | null;
    extracted_payload: Record<string, unknown>;
    created_at?: string;
  }>;
};

export type EmailMailboxDriver = "imap" | "gmail_api";

/** Public shape from API (secrets are null; use has_* flags). */
export type EmailMailboxConnectionConfig = {
  host?: string | null;
  port?: number | null;
  encryption?: "ssl" | "tls" | "none" | string | null;
  username?: string | null;
  password?: string | null;
  folder?: string | null;
  client_id?: string | null;
  client_secret?: string | null;
  refresh_token?: string | null;
};

export type EmailMailbox = {
  id: string;
  organization_id: string;
  name: string;
  driver: EmailMailboxDriver;
  is_enabled: boolean;
  connection_config: EmailMailboxConnectionConfig;
  has_password: boolean;
  has_client_secret: boolean;
  has_refresh_token: boolean;
  last_polled_at: string | null;
  last_successful_sync_at: string | null;
  last_error: string | null;
  created_at?: string;
  updated_at?: string;
};

export type OrganizationSmtpSettings = {
  host: string | null;
  port: number | null;
  username: string | null;
  encryption: "tls" | "ssl" | null;
  from_address: string | null;
  from_name: string | null;
  reply_to_address: string | null;
  reply_to_name: string | null;
  timeout: number | null;
  local_domain: string | null;
  has_password: boolean;
};

export type MainSmtpSettings = {
  host: string | null;
  port: number | null;
  username: string | null;
  encryption: "tls" | "ssl" | null;
  from_address: string | null;
  from_name: string | null;
  reply_to_address: string | null;
  reply_to_name: string | null;
  timeout: number | null;
  local_domain: string | null;
  has_password: boolean;
};

export type MonitoringCheck = {
  id: string;
  organization_id: string;
  vendor_id: string | null;
  name: string;
  type: string;
  endpoint: string | null;
  configuration: Record<string, unknown> | null;
  interval_seconds: number;
  enabled: boolean;
  last_status: string | null;
  last_message: string | null;
  last_http_status: number | null;
  last_response_time_ms: number | null;
  domain: string | null;
  domain_expires_at: string | null;
  domain_days_remaining: number | null;
  domain_registrar: string | null;
  ssl_expires_at: string | null;
  ssl_days_remaining: number | null;
  consecutive_failures?: number;
  last_run_at: string | null;
  next_run_at: string | null;
  uptime_since: string | null;
  created_at?: string;
  updated_at?: string;
};

export type MonitoringCheckReassignResult = {
  mode: "dry-run" | "execute";
  check_id: string;
  check_name: string;
  from_organization_id: string;
  to_organization_id: string;
  monitoring_logs_to_move: number;
  domain_social_accounts_to_move: number;
  updated_checks?: number;
  updated_monitoring_logs?: number;
  updated_domain_social_accounts?: number;
};

export type MonitoringLog = {
  id: string;
  monitoring_check_id: string;
  organization_id: string;
  status: string;
  http_status: number | null;
  response_time_ms: number | null;
  message: string | null;
  meta: Record<string, unknown> | null;
  created_at?: string;
};

export type DomainSocialAccount = {
  id: string;
  monitoring_check_id: string;
  organization_id: string;
  platform_name: string;
  social_handle_or_url: string;
  last_follower_count: number;
  updated_at?: string;
};

export type MonitoringLogSummary = {
  window_from: string;
  window_to: string;
  window_span_seconds: number;
  log_count_in_window: number;
  probe_counts: Record<string, number>;
  duration_seconds: {
    up: number;
    down: number;
    degraded: number;
    skipped: number;
    unknown: number;
  };
  uptime_ratio: number | null;
  downtime_incidents: number;
};

export type MonitoringServerAnalyticsMetric = {
  avg: number | null;
  min: number | null;
  max: number | null;
  latest: number | null;
};

export type MonitoringServerAnalytics = {
  window_from: string;
  window_to: string;
  sample_count: number;
  metrics: Record<string, MonitoringServerAnalyticsMetric>;
};

export type ExperienceMonitoringTest = {
  id: string;
  organization_id: string;
  name: string;
  login_url: string;
  login_username: string;
  dashboard_url: string;
  interval_seconds: number;
  browser_type: ExperienceMonitoringBrowserType;
  timeout_ms: number;
  concurrent_sessions: number | null;
  configuration: Record<string, unknown> | null;
  thresholds: Record<string, unknown> | null;
  enabled: boolean;
  last_status: string | null;
  last_error: string | null;
  last_run_at: string | null;
  next_run_at: string | null;
  created_at?: string;
  updated_at?: string;
};

export type ExperienceMonitoringRun = {
  id: string;
  experience_monitoring_test_id: string;
  organization_id: string;
  session_index: number;
  status: string;
  login_duration_ms: number | null;
  dashboard_load_duration_ms: number | null;
  total_duration_ms: number | null;
  http_status: number | null;
  failed_requests_count: number;
  js_errors_count: number;
  screenshot_path: string | null;
  http_status_codes: Array<Record<string, unknown>> | null;
  response_times: Array<Record<string, unknown>> | null;
  browser_logs: Array<Record<string, unknown>> | null;
  error_message: string | null;
  started_at: string | null;
  finished_at: string | null;
  created_at?: string;
};

export type ExperienceMonitoringScreenshot = {
  id: string;
  experience_monitoring_run_id: string;
  organization_id: string;
  path: string;
  mime_type: string;
  size_bytes: number | null;
  captured_at: string | null;
  created_at?: string;
};

export type ExperienceMonitoringMetricsSummary = {
  window_from: string;
  window_to: string;
  sample_count: number;
  login_duration_ms: {
    avg: number | null;
    min: number | null;
    max: number | null;
    p95: number | null;
  };
  dashboard_load_duration_ms: {
    avg: number | null;
    min: number | null;
    max: number | null;
    p95: number | null;
  };
  total_duration_ms: {
    avg: number | null;
    min: number | null;
    max: number | null;
    p95: number | null;
  };
};

export type ExperienceMonitoringTechnicalReport = {
  generated_at: string;
  window_from: string;
  window_to: string;
  sample_count: number;
  severity: "S0" | "S1" | "S2" | "S3" | "S4";
  risk_score: number;
  score_breakdown: {
    availability: number;
    performance: number;
    frontend: number;
    security: number;
  };
  journey: {
    success_rate_pct: number | null;
    status_breakdown: Record<string, number>;
    metrics: {
      login_duration_ms: {
        avg: number | null;
        min: number | null;
        max: number | null;
        p95: number | null;
      };
      dashboard_load_duration_ms: {
        avg: number | null;
        min: number | null;
        max: number | null;
        p95: number | null;
      };
      total_duration_ms: {
        avg: number | null;
        min: number | null;
        max: number | null;
        p95: number | null;
      };
    };
    failed_requests_avg: number | null;
    js_errors_avg: number | null;
    http_status_distribution: Record<string, number>;
    top_js_errors: Array<{
      message: string;
      count: number;
    }>;
  };
  network: {
    dns: {
      host: string;
      lookup_latency_ms: number;
      resolver_nameservers: string[];
      a_records: string[];
      aaaa_records: string[];
      ttl_min: number | null;
      ttl_max: number | null;
    } | null;
    tls: {
      handshake_ms: number | null;
      protocol?: string | null;
      cipher_name?: string | null;
      issuer_cn?: string | null;
      subject_cn?: string | null;
      valid_to?: string | null;
      certificate_days_remaining: number | null;
      error?: string | null;
    } | null;
    security_headers: {
      url: string;
      status_code: number | null;
      request_latency_ms: number | null;
      server: string | null;
      headers: Record<string, string | null>;
      missing_critical: string[];
      error?: string;
    };
    latest_speedtest: {
      id: string;
      tested_at: string | null;
      status_code: number | null;
      success: boolean;
      error: string | null;
      total_time_ms: number | null;
      ttfb_ms: number | null;
      dns_lookup_ms: number | null;
      tcp_connect_ms: number | null;
      tls_handshake_ms: number | null;
      download_speed_kbps: number | null;
      checked_from: string | null;
    } | null;
  };
  findings: Array<{
    title: string;
    severity: string;
    detail: string;
  }>;
  suggestions: string[];
};

export type UrlCheckerMode = "quick" | "site_crawl";

export type UrlCheckerCrawlMeta = {
  pages_crawled: number;
  max_pages: number;
  max_depth: number;
  max_total_links: number;
  stopped_reason: "completed" | "max_pages" | "max_depth" | "max_total_links" | "timeout";
  unreachable_pages: number;
};

export type UrlCheckerReportRow = {
  source_url: string;
  discovered_link: string;
  status_code: number | null;
  status: "Active" | "Broken";
  response_time_ms: number | null;
  link_type: "internal" | "external";
  error: string | null;
};

export type UrlCheckerReport = {
  target_url: string;
  mode: UrlCheckerMode;
  scanned_at: string;
  crawl?: UrlCheckerCrawlMeta;
  summary: {
    total_links: number;
    active_links: number;
    broken_links: number;
    average_response_time_ms: number;
  };
  links: UrlCheckerReportRow[];
};

export type WebsiteSpeedtestReport = {
  run_id?: string;
  persisted?: boolean;
  persistence_note?: string;
  target_url: string;
  tested_at: string;
  checked_from?: string;
  final_url: string | null;
  status_code: number | null;
  success: boolean;
  error: string | null;
  timeout_seconds: number;
  metrics: {
    total_time_ms: number | null;
    ttfb_ms: number | null;
    dns_lookup_ms: number | null;
    tcp_connect_ms: number | null;
    tls_handshake_ms: number | null;
    redirect_time_ms: number | null;
    download_speed_kbps: number | null;
    downloaded_bytes: number | null;
  };
};

export type WebsiteSpeedtestRun = {
  id: string;
  organization_id: string;
  organization_name?: string | null;
  created_by: string | null;
  target_url: string;
  checked_from: string | null;
  final_url: string | null;
  status_code: number | null;
  success: boolean;
  error: string | null;
  timeout_seconds: number;
  total_time_ms: number | null;
  ttfb_ms: number | null;
  dns_lookup_ms: number | null;
  tcp_connect_ms: number | null;
  tls_handshake_ms: number | null;
  redirect_time_ms: number | null;
  download_speed_kbps: number | null;
  downloaded_bytes: number | null;
  metrics: Record<string, unknown> | null;
  tested_at: string | null;
  created_at?: string;
  updated_at?: string;
};

export type DnsCheckItem = {
  key: string;
  title: string;
  status: "pass" | "warning" | "fail";
  severity: "S0" | "S1" | "S2" | "S3" | "S4" | string;
  explanation: string;
  suggestion: string;
  evidence: string[];
};

export type DnsCheckReport = {
  target: string;
  host: string;
  scanned_at: string;
  resolver_nameservers: string[];
  summary: {
    total_checks: number;
    passed: number;
    warnings: number;
    failed: number;
  };
  records: {
    a: string[];
    aaaa: string[];
    ns: string[];
    parent_delegation_ns?: string[];
    mx: string[];
    txt: string[];
    soa: string[];
    cname: string[];
    caa: string[];
    dmarc: string[];
    dnskey: string[];
    www?: {
      a: string[];
      aaaa: string[];
      cname: string[];
    };
    resolver_consensus?: {
      local_a: string[];
      google_a: string[];
      cloudflare_a: string[];
      local_vs_google_match: boolean;
      local_vs_cloudflare_match: boolean;
      google_error: string | null;
      cloudflare_error: string | null;
    };
    nameserver_host_resolution?: Record<string, { a: string[]; aaaa: string[] }>;
  };
  checks: DnsCheckItem[];
};

export type PortCheckerResult = {
  port: number;
  state: "open" | "closed" | "filtered";
  latency_ms: number | null;
  service_hint: string;
  banner: string | null;
  risk: "low" | "medium" | "high" | null;
  error: string | null;
};

export type PortCheckerFinding = {
  key: string;
  title: string;
  status: "pass" | "warning" | "fail";
  severity: "S0" | "S1" | "S2" | "S3" | "S4" | string;
  explanation: string;
  suggestion: string;
  evidence: string[];
};

export type PortCheckerReport = {
  target: string;
  host: string;
  mode: "quick" | "extended" | string;
  scanned_at: string;
  timeout_ms: number;
  edge_provider?: {
    name: string;
    confidence: string;
    evidence: string[];
    note: string;
  };
  summary: {
    total_ports: number;
    open_ports: number;
    closed_ports: number;
    filtered_ports: number;
    risky_open_ports: number;
  };
  ports: PortCheckerResult[];
  findings: PortCheckerFinding[];
};

export type SeoAuditMode = "quick" | "site_crawl";

export type SeoAuditItem = {
  key: string;
  title: string;
  message: string;
  suggestion: string | null;
};

export type SeoCrawlMeta = {
  pages_crawled: number;
  max_pages: number;
  max_depth: number;
  stopped_reason: "completed" | "max_pages" | "max_depth" | "timeout";
  unreachable_pages: number;
};

export type SeoIssueRollup = {
  key: string;
  title: string;
  page_count: number;
  urls: string[];
};

export type SeoSitePageSummary = {
  url: string;
  overall_score: number;
  totals: {
    passed: number;
    warnings: number;
    critical: number;
  };
  top_critical: string | null;
  unreachable: boolean;
};

export type SeoSiteSummary = {
  average_score: number;
  lowest_score: number;
  lowest_url: string | null;
  total_critical: number;
  total_warnings: number;
};

export type OnPageSeoAuditReport = {
  target_url: string;
  mode: SeoAuditMode;
  scanned_at: string;
  overall_score: number;
  totals: {
    passed: number;
    warnings: number;
    critical: number;
  };
  passed_audits: SeoAuditItem[];
  warnings: SeoAuditItem[];
  critical_fixes: SeoAuditItem[];
  crawl?: SeoCrawlMeta;
  site_summary?: SeoSiteSummary;
  issue_rollups?: SeoIssueRollup[];
  pages?: SeoSitePageSummary[];
  metrics: {
    title_length?: number | null;
    meta_description_length?: number | null;
    h1_count?: number;
    h2_count?: number;
    h3_count?: number;
    images_total?: number;
    images_missing_alt?: number;
    images_missing_alt_percent?: number;
    internal_link_count?: number;
    word_count?: number;
    has_json_ld?: boolean;
    pages_audited?: number;
    open_graph_present?: Record<string, boolean>;
  };
  extracted_values: {
    title: string | null;
    meta_description: string | null;
    canonical?: string | null;
    robots?: string | null;
    viewport?: string | null;
    html_lang?: string | null;
    favicon?: string | null;
    final_url?: string | null;
    headings: {
      h1: string[];
      h2: string[];
      h3: string[];
    };
    open_graph: Record<string, string | null>;
  };
};

export type DashboardTrendPointMonitoring = {
  date: string;
  total_runs: number;
  downtime_runs: number;
  availability_ratio: number | null;
};

export type DashboardTrendPointInvoices = {
  date: string;
  issued_count: number;
  issued_amount_cents: number;
  paid_count: number;
  paid_amount_cents: number;
};

export type DashboardTrends = {
  range: {
    from: string;
    to: string;
    days: number;
  };
  monitoring: DashboardTrendPointMonitoring[];
  invoices: DashboardTrendPointInvoices[];
};

export type DashboardMonitoringCreateFallbackPoint = {
  date: string;
  count: number;
  by_source: {
    user_default: number;
    membership_first: number;
    dev_database_fallback: number;
    other: number;
  };
};

export type DashboardMonitoringCreateFallbackTrends = {
  range: {
    from: string;
    to: string;
    days: number;
  };
  total: number;
  series: DashboardMonitoringCreateFallbackPoint[];
};

export type AppNotification = {
  id: string;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
};

export type PaginatedMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type ApiListResponse<T> = {
  success: boolean;
  message: string;
  data: T[];
  links?: Record<string, string | null>;
  meta?: PaginatedMeta;
};

export type ApiSuccessResponse<T> = {
  success: boolean;
  message: string;
  data: T;
};
