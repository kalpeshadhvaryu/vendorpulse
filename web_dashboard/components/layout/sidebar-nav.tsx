"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  ChevronDown,
  PanelLeft,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Separator } from "@/components/ui/separator";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { getCategoryLinksForUser, rootLinks } from "@/lib/navigation/main-menu";
import { collectNavHrefs, isNavHrefActive } from "@/lib/navigation/route-match";
import { useAuthStore } from "@/stores/auth-store";

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname() ?? "/";
  const router = useRouter();
  const isGlobalAdmin = useAuthStore((s) => Boolean(s.user?.is_admin));
  const visibleCategoryLinks = useMemo(
    () => getCategoryLinksForUser(isGlobalAdmin),
    [isGlobalAdmin],
  );
  const navHrefs = useMemo(
    () =>
      collectNavHrefs(
        rootLinks.map((item) => item.href),
        visibleCategoryLinks,
      ),
    [visibleCategoryLinks],
  );

  useEffect(() => {
    const routeSet = new Set<string>();
    rootLinks.forEach((item) => routeSet.add(item.href));
    visibleCategoryLinks.forEach((section) => {
      routeSet.add(section.href);
      section.children.forEach((child) => {
        if (child.href) {
          routeSet.add(child.href);
        }
      });
    });

    routeSet.forEach((href) => {
      router.prefetch(href);
    });
  }, [router, visibleCategoryLinks]);

  const initiallyExpanded = useMemo(() => {
    const defaults: Record<string, boolean> = {};
    visibleCategoryLinks.forEach((section) => {
      defaults[section.id] = section.children.some(
        (item) => item.href && isNavHrefActive(pathname, item.href, navHrefs),
      );
      if (!defaults[section.id]) {
        defaults[section.id] = true;
      }
    });
    return defaults;
  }, [pathname, visibleCategoryLinks, navHrefs]);

  const [expanded, setExpanded] = useState<Record<string, boolean>>(initiallyExpanded);

  const toggleSection = (sectionId: string) => {
    setExpanded((prev) => ({ ...prev, [sectionId]: !prev[sectionId] }));
  };

  return (
    <nav className="flex flex-col gap-2 px-2 py-3">
      {rootLinks.map(({ href, label, icon: Icon }) => {
        const active = isNavHrefActive(pathname, href, navHrefs);
        return (
          <Link
            key={href}
            href={href}
            onClick={onNavigate}
            className={cn(
              "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              active
                ? "bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-muted hover:text-foreground",
            )}
          >
            <Icon className="h-4 w-4 shrink-0" />
            {label}
          </Link>
        );
      })}

      <div className="px-2 pt-2">
        <div className="h-px bg-border/60" />
      </div>

      {visibleCategoryLinks.map(({ id, label, href, icon: Icon, children }) => {
        const sectionActive = children.some(
          (item) => item.href && isNavHrefActive(pathname, item.href, navHrefs),
        );
        const isOpen = expanded[id] ?? true;

        return (
          <div key={id} className="space-y-1 rounded-lg border border-border/60 bg-card/60 p-1.5">
            <button
              type="button"
              onClick={() => {
                toggleSection(id);
                router.push(href);
                onNavigate?.();
              }}
              className={cn(
                "flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm font-medium transition-colors",
                sectionActive
                  ? "text-foreground"
                  : "text-muted-foreground hover:bg-muted hover:text-foreground",
              )}
              aria-expanded={isOpen}
            >
              <span className="flex items-center gap-2">
                <Icon className="h-4 w-4 shrink-0" />
                {label}
              </span>
              <ChevronDown className={cn("h-4 w-4 transition-transform", isOpen ? "rotate-180" : "rotate-0")} />
            </button>

            {isOpen ? (
              <div className="space-y-0.5 px-1 pb-1">
                {children.map((item) => {
                  const childActive =
                    item.href !== undefined && isNavHrefActive(pathname, item.href, navHrefs);
                  const ChildIcon = item.icon;

                  if (item.placeholder || !item.href) {
                    return (
                      <div
                        key={`${id}-${item.label}`}
                        className="flex items-center justify-between rounded-md px-2 py-1.5 text-xs text-muted-foreground"
                      >
                        <span className="flex items-center gap-2">
                          <ChildIcon className="h-3.5 w-3.5" />
                          {item.label}
                        </span>
                        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide">
                          Soon
                        </span>
                      </div>
                    );
                  }

                  return (
                    <Link
                      key={`${id}-${item.href}`}
                      href={item.href}
                      onClick={onNavigate}
                      className={cn(
                        "flex items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition-colors",
                        childActive
                          ? "bg-primary/10 text-primary"
                          : "text-muted-foreground hover:bg-muted hover:text-foreground",
                      )}
                    >
                      <ChildIcon className="h-3.5 w-3.5 shrink-0" />
                      {item.label}
                    </Link>
                  );
                })}
              </div>
            ) : null}
          </div>
        );
      })}
    </nav>
  );
}

export function SidebarNav() {
  return (
    <aside className="hidden w-60 shrink-0 border-r border-border/60 bg-card lg:flex lg:flex-col">
      <div className="flex h-14 items-center gap-2 border-b border-border/60 px-4">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">
          VP
        </div>
        <div className="flex flex-col leading-tight">
          <span className="text-sm font-semibold">VendorPulse</span>
          <span className="text-[11px] text-muted-foreground">Operations</span>
        </div>
      </div>
      <ScrollArea className="flex-1">
        <NavLinks />
      </ScrollArea>
      <Separator />
      <div className="p-3 text-[11px] text-muted-foreground">API v1 · Laravel Sanctum</div>
    </aside>
  );
}

export function MobileNav() {
  const [open, setOpen] = useState(false);
  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <Button variant="ghost" size="icon" className="lg:hidden" aria-label="Open menu">
          <PanelLeft className="h-5 w-5" />
        </Button>
      </SheetTrigger>
      <SheetContent side="left" className="w-72 p-0">
        <SheetHeader className="border-b border-border/60 px-4 py-3 text-left">
          <SheetTitle className="text-base">VendorPulse</SheetTitle>
        </SheetHeader>
        <NavLinks onNavigate={() => setOpen(false)} />
      </SheetContent>
    </Sheet>
  );
}
