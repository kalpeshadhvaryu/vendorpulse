/**
 * Public API base URL (must match axios `baseURL` in client.ts).
 * Kept in a tiny module so error helpers can import it without pulling in axios.
 *
 * Default uses 127.0.0.1 (not "localhost") so the browser matches `php artisan serve`,
 * which binds 127.0.0.1 by default. On many systems "localhost" can resolve to ::1
 * first and hit a different listener than curl to 127.0.0.1.
 */
const raw = process.env.NEXT_PUBLIC_API_URL?.trim().replace(/\/$/, "") ?? "";

export const publicApiBaseUrl = raw || "http://127.0.0.1:8000/api/v1";

export const publicApiUrlFromEnv = raw.length > 0;
