"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { LogOut, Settings, User } from "lucide-react";
import { MobileNav, SidebarNav } from "@/components/layout/sidebar-nav";
import { OrgSwitcher } from "@/components/layout/org-switcher";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Separator } from "@/components/ui/separator";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { useAuthStore } from "@/stores/auth-store";

const titles: Record<string, string> = {
  "/dashboard": "Dashboard",
  "/admin": "Administration",
  "/admin/organizations": "Organizations",
  "/admin/users": "Users",
  "/vendors": "Vendors",
  "/invoices": "Invoices",
  "/email-activity": "Email activity",
  "/monitoring": "Monitoring",
  "/monitoring/experience": "VAPT",
  "/notifications": "Notifications",
  "/settings": "Settings",
  "/docs": "Documentation",
};

function titleFromPath(pathname: string | null): string {
  const p = pathname ?? "/";
  if (p.startsWith("/docs")) {
    return "Documentation";
  }
  if (p.startsWith("/settings/mailboxes")) {
    return "Mailboxes";
  }
  if (p.startsWith("/email-activity")) {
    return "Email activity";
  }
  if (p.startsWith("/admin/organizations/")) {
    return "Organization";
  }
  if (p.startsWith("/admin/users/")) {
    return "User";
  }
  if (p.startsWith("/admin/organizations")) {
    return "Organizations";
  }
  if (p.startsWith("/admin/users")) {
    return "Users";
  }
  if (p.startsWith("/admin")) {
    return "Administration";
  }
  if (p.startsWith("/monitoring/experience")) {
    return "VAPT";
  }
  if (p.startsWith("/monitoring")) {
    return "Monitoring";
  }
  if (titles[p]) return titles[p];
  const base = `/${p.split("/")[1] ?? ""}`;
  return titles[base] ?? "VendorPulse";
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);

  return (
    <div className="flex min-h-screen w-full bg-background">
      <SidebarNav />
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-border/60 bg-background/80 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/70">
          <div className="flex min-w-0 flex-1 items-center gap-2">
            <MobileNav />
            <span className="truncate text-sm font-semibold tracking-tight">{titleFromPath(pathname)}</span>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <OrgSwitcher />
            <Separator orientation="vertical" className="hidden h-6 sm:block" />
            <ThemeToggle />
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="relative h-9 gap-2 rounded-full px-2">
                  <Avatar className="h-8 w-8">
                    <AvatarFallback>
                      {user?.name
                        ?.split(" ")
                        .map((n) => n[0])
                        .join("")
                        .slice(0, 2)
                        .toUpperCase() ?? "U"}
                    </AvatarFallback>
                  </Avatar>
                  <span className="hidden max-w-[120px] truncate text-sm font-medium sm:inline">{user?.name}</span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="font-normal">
                  <div className="flex flex-col space-y-1">
                    <p className="text-sm font-medium leading-none">{user?.name}</p>
                    <p className="text-xs leading-none text-muted-foreground">{user?.email}</p>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild className="gap-2">
                  <Link href="/settings">
                    <Settings className="h-4 w-4" />
                    Settings
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuItem disabled className="gap-2">
                  <User className="h-4 w-4" />
                  Profile (API)
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="gap-2 text-destructive focus:text-destructive"
                  onClick={async () => {
                    await logout();
                    router.replace("/login");
                  }}
                >
                  <LogOut className="h-4 w-4" />
                  Log out
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </header>
        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">{children}</main>
      </div>
    </div>
  );
}
