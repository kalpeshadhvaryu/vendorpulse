"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import { DOC_PAGES } from "@/lib/docs-nav";

export function DocNav() {
  const pathname = usePathname() ?? "/";

  return (
    <nav className="flex flex-col gap-0.5 p-3 lg:p-4">
      <p className="mb-2 px-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Guide</p>
      {DOC_PAGES.map(({ href, label, icon: Icon }) => {
        const active = href === "/docs" ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
        return (
          <Link
            key={href}
            href={href}
            className={cn(
              "flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition-colors",
              active
                ? "bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-muted hover:text-foreground",
            )}
          >
            <Icon className="h-4 w-4 shrink-0 opacity-80" />
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
