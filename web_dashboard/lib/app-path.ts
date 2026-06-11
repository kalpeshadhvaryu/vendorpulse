/** Must match `basePath` in next.config.ts */
export const APP_BASE_PATH = "/web_dashboard";

export function appPath(path: string): string {
  const normalized = path.startsWith("/") ? path : `/${path}`;
  if (normalized === APP_BASE_PATH || normalized.startsWith(`${APP_BASE_PATH}/`)) {
    return normalized;
  }
  return `${APP_BASE_PATH}${normalized}`;
}
