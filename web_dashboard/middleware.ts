import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const SESSION_COOKIE = "vp_session";
const BASE_PATH = "/web_dashboard";

function stripBasePath(pathname: string): string {
  if (pathname === BASE_PATH) {
    return "/";
  }

  if (pathname.startsWith(`${BASE_PATH}/`)) {
    return pathname.slice(BASE_PATH.length);
  }

  return pathname;
}

function withBasePath(pathname: string): string {
  if (pathname === "/") {
    return BASE_PATH;
  }

  if (pathname.startsWith(BASE_PATH)) {
    return pathname;
  }

  return `${BASE_PATH}${pathname.startsWith("/") ? pathname : `/${pathname}`}`;
}

function redirectTo(request: NextRequest, pathname: string) {
  const url = request.nextUrl.clone();
  url.pathname = withBasePath(pathname);
  url.search = "";
  return NextResponse.redirect(url);
}

export function middleware(request: NextRequest) {
  try {
    const pathname = stripBasePath(request.nextUrl.pathname);
    const hasSession = Boolean(request.cookies.get(SESSION_COOKIE)?.value);

    if (pathname === "/") {
      return NextResponse.next();
    }

    // Do not redirect /login → /dashboard from cookie alone. `vp_session` can outlive
    // cleared localStorage; Zustand then has no token and AuthGuard sends users back
    // to /login, which would loop forever if we forced them to /dashboard here.
    if (pathname === "/login") {
      return NextResponse.next();
    }

    if (!hasSession) {
      return redirectTo(request, "/login");
    }

    return NextResponse.next();
  } catch {
    return NextResponse.next();
  }
}

export const config = {
  matcher: [
    "/web_dashboard",
    "/web_dashboard/login",
    "/web_dashboard/dashboard",
    "/web_dashboard/dashboard/:path*",
    "/web_dashboard/admin",
    "/web_dashboard/admin/:path*",
    "/web_dashboard/vendors",
    "/web_dashboard/vendors/:path*",
    "/web_dashboard/monitoring",
    "/web_dashboard/monitoring/:path*",
    "/web_dashboard/vapt-web-health",
    "/web_dashboard/vapt-web-health/:path*",
    "/web_dashboard/marketing-seo",
    "/web_dashboard/marketing-seo/:path*",
    "/web_dashboard/operations-management",
    "/web_dashboard/operations-management/:path*",
    "/web_dashboard/performance-health",
    "/web_dashboard/performance-health/:path*",
    "/web_dashboard/platform-support",
    "/web_dashboard/platform-support/:path*",
    "/web_dashboard/cybersecurity-vapt",
    "/web_dashboard/cybersecurity-vapt/:path*",
    "/web_dashboard/marketing-seo-hub",
    "/web_dashboard/marketing-seo-hub/:path*",
    "/web_dashboard/settings",
    "/web_dashboard/settings/:path*",
    "/web_dashboard/notifications",
    "/web_dashboard/notifications/:path*",
    "/web_dashboard/invoices",
    "/web_dashboard/invoices/:path*",
    "/web_dashboard/docs",
    "/web_dashboard/docs/:path*",
  ],
};
