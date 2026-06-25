import type { MonitoringCheck } from "@/lib/api/types";

export const DOMAIN_MONITORING_TYPES = new Set(["domain", "whois"]);

export const DOMAIN_EXPIRY_WARNING_DAYS = 30;

export function isDomainMonitoringCheck(type: string | null | undefined): boolean {
  return DOMAIN_MONITORING_TYPES.has((type ?? "").toLowerCase());
}

export function formatDomainExpiryDate(value: string | null | undefined): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

export function formatDomainDaysRemaining(days: number | null | undefined): string {
  if (days == null) {
    return "—";
  }

  if (days < 0) {
    return `Expired ${Math.abs(days)}d ago`;
  }

  if (days === 0) {
    return "Expires today";
  }

  return `${days}d`;
}

export function domainDaysTone(
  days: number | null | undefined,
): "success" | "warning" | "destructive" | "secondary" {
  if (days == null) {
    return "secondary";
  }

  if (days < 0) {
    return "destructive";
  }

  if (days <= DOMAIN_EXPIRY_WARNING_DAYS) {
    return "warning";
  }

  return "success";
}

export function isDomainExpiryAttention(check: MonitoringCheck): boolean {
  if (!isDomainMonitoringCheck(check.type)) {
    return false;
  }

  if (check.domain_days_remaining == null) {
    return (check.last_status ?? "").toLowerCase() === "degraded";
  }

  return check.domain_days_remaining <= DOMAIN_EXPIRY_WARNING_DAYS;
}

export function listDomainExpiryChecks(checks: MonitoringCheck[]): MonitoringCheck[] {
  return checks
    .filter((check) => isDomainMonitoringCheck(check.type))
    .filter((check) => check.domain_days_remaining != null || check.domain_expires_at != null)
    .sort((a, b) => {
      const aDays = a.domain_days_remaining ?? Number.MAX_SAFE_INTEGER;
      const bDays = b.domain_days_remaining ?? Number.MAX_SAFE_INTEGER;

      return aDays - bDays;
    });
}

export function listDomainExpiryAlerts(checks: MonitoringCheck[]): MonitoringCheck[] {
  return listDomainExpiryChecks(checks).filter(isDomainExpiryAttention);
}

export function readDomainProbeMeta(
  source: Record<string, unknown> | null | undefined,
): Pick<MonitoringCheck, "domain" | "domain_expires_at" | "domain_days_remaining" | "domain_registrar"> {
  if (!source) {
    return {
      domain: null,
      domain_expires_at: null,
      domain_days_remaining: null,
      domain_registrar: null,
    };
  }

  const days = source.domain_days_remaining;

  return {
    domain: typeof source.domain === "string" ? source.domain : null,
    domain_expires_at: typeof source.domain_expires_at === "string" ? source.domain_expires_at : null,
    domain_days_remaining: typeof days === "number" ? days : null,
    domain_registrar:
      typeof source.registrar === "string"
        ? source.registrar
        : typeof source.domain_registrar === "string"
          ? source.domain_registrar
          : null,
  };
}

export function formatCheckStatusLabel(
  check: MonitoringCheck,
  options?: { showDomainExpiryDays?: boolean },
): string {
  const status = check.last_status ?? "—";

  if (options?.showDomainExpiryDays !== true || !isDomainMonitoringCheck(check.type) || check.domain_days_remaining == null) {
    return status;
  }

  const normalized = status.toLowerCase();
  if (normalized === "failed" && check.domain_days_remaining < 0) {
    return "failed · expired";
  }

  return `${status} · ${formatDomainDaysRemaining(check.domain_days_remaining)}`;
}

export function formatLogStatusLabel(
  status: string,
  meta: Record<string, unknown> | null | undefined,
  isDomainCheck: boolean,
): string {
  const normalized = status.toLowerCase();
  if (!isDomainCheck) {
    return status;
  }

  const probe = readDomainProbeMeta(meta);
  if (probe.domain_days_remaining == null) {
    return status;
  }

  if (normalized === "failed" && probe.domain_days_remaining < 0) {
    return "failed · expired";
  }

  if (normalized === "ok" || normalized === "degraded") {
    return `${status} · ${formatDomainDaysRemaining(probe.domain_days_remaining)}`;
  }

  return status;
}
