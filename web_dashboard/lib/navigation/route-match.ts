export function normalizePathname(pathname: string): string {
  const base = pathname.split("?")[0]?.split("#")[0] ?? "/";
  if (base.length > 1 && base.endsWith("/")) {
    return base.slice(0, -1);
  }
  return base || "/";
}

export function matchesRoutePath(pathname: string, href: string): boolean {
  const path = normalizePathname(pathname);
  const route = normalizePathname(href);
  return path === route || path.startsWith(`${route}/`);
}

/**
 * A nav href is active when it matches the pathname and no other registered href
 * is a more specific prefix match (e.g. /monitoring must not win over /monitoring/experience).
 */
export function isNavHrefActive(pathname: string, href: string, allHrefs: string[]): boolean {
  if (!matchesRoutePath(pathname, href)) {
    return false;
  }

  const route = normalizePathname(href);

  return !allHrefs.some((other) => {
    if (other === href) {
      return false;
    }

    const otherRoute = normalizePathname(other);
    return otherRoute.startsWith(`${route}/`) && matchesRoutePath(pathname, otherRoute);
  });
}

export function collectNavHrefs(
  rootHrefs: string[],
  categories: Array<{ href: string; children: Array<{ href?: string }> }>,
): string[] {
  const hrefs = new Set<string>(rootHrefs);

  categories.forEach((section) => {
    hrefs.add(section.href);
    section.children.forEach((child) => {
      if (child.href) {
        hrefs.add(child.href);
      }
    });
  });

  return Array.from(hrefs);
}
