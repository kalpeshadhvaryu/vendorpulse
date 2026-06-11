export const EXPERIENCE_MONITORING_BROWSERS = ["chromium"] as const;

export type ExperienceMonitoringBrowserType = (typeof EXPERIENCE_MONITORING_BROWSERS)[number];

export const DEFAULT_EXPERIENCE_MONITORING_BROWSER: ExperienceMonitoringBrowserType = "chromium";

export function normalizeExperienceMonitoringBrowser(
  value: string | null | undefined,
): ExperienceMonitoringBrowserType {
  if (value && (EXPERIENCE_MONITORING_BROWSERS as readonly string[]).includes(value)) {
    return value as ExperienceMonitoringBrowserType;
  }

  return DEFAULT_EXPERIENCE_MONITORING_BROWSER;
}
