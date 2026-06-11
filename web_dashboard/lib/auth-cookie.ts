const SESSION_COOKIE = "vp_session";
const MAX_AGE = 60 * 60 * 24 * 7;

export function setSessionCookie() {
  if (typeof document === "undefined") return;
  document.cookie = `${SESSION_COOKIE}=1; Path=/; Max-Age=${MAX_AGE}; SameSite=Lax`;
}

export function clearSessionCookie() {
  if (typeof document === "undefined") return;
  document.cookie = `${SESSION_COOKIE}=; Path=/; Max-Age=0; SameSite=Lax`;
}
