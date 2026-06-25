import type { LucideIcon } from "lucide-react";
import {
  Activity,
  Bell,
  BookText,
  Building2,
  FileSpreadsheet,
  Gauge,
  Mail,
  Globe,
  LayoutDashboard,
  Link2,
  LockKeyhole,
  Megaphone,
  Settings,
  ShieldAlert,
  Users,
} from "lucide-react";

export type SidebarChildItem = {
  label: string;
  href?: string;
  icon: LucideIcon;
  placeholder?: boolean;
  /** Hidden from sidebar and section hubs unless the user is a global admin. */
  globalAdminOnly?: boolean;
};

export type SidebarCategory = {
  id: string;
  label: string;
  href: string;
  icon: LucideIcon;
  children: SidebarChildItem[];
};

export const rootLinks: Array<{ href: string; label: string; icon: LucideIcon }> = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
];

export const categoryLinks: SidebarCategory[] = [
  {
    id: "operations-management",
    label: "Operations & Management",
    href: "/operations-management",
    icon: ShieldAlert,
    children: [
      { href: "/admin", label: "Administration", icon: Users, globalAdminOnly: true },
      { href: "/vendors", label: "Vendors", icon: Building2 },
      { href: "/invoices", label: "Invoices", icon: FileSpreadsheet },
      { href: "/email-activity", label: "Email activity", icon: Mail },
    ],
  },
  {
    id: "performance-health",
    label: "Performance & Health",
    href: "/performance-health",
    icon: Activity,
    children: [
      { href: "/monitoring", label: "Monitoring", icon: Activity },
    ],
  },
  {
    id: "cybersecurity-vapt",
    label: "Cybersecurity & VAPT",
    href: "/cybersecurity-vapt",
    icon: LockKeyhole,
    children: [
      { href: "/monitoring/experience", label: "VAPT", icon: Activity },
      { href: "/vapt-web-health/url-checker", label: "Link/Website Auditor", icon: Link2 },
      { href: "/vapt-web-health", label: "Web Health", icon: Globe },
      { href: "/vapt-web-health/dns-check", label: "DNS Check", icon: Globe },
      { href: "/vapt-web-health/port-checker", label: "Port Checker", icon: ShieldAlert },
      { href: "/vapt-web-health/website-speedtest", label: "Website Speedtest", icon: Gauge },
      { label: "Security Headers & SSL", icon: LockKeyhole, placeholder: true },
    ],
  },
  {
    id: "marketing-seo",
    label: "Marketing & SEO",
    href: "/marketing-seo-hub",
    icon: Megaphone,
    children: [
      { href: "/marketing-seo/on-page-seo-scorecard", label: "On-Page SEO", icon: Globe },
      { label: "Social Account Mapping", icon: Link2, placeholder: true },
    ],
  },
  {
    id: "platform-support",
    label: "Platform & Support",
    href: "/platform-support",
    icon: Settings,
    children: [
      { href: "/notifications", label: "Notifications", icon: Bell },
      { href: "/settings", label: "Settings", icon: Settings },
      { href: "/docs", label: "Documentation", icon: BookText },
    ],
  },
];

export function getCategoryLinksForUser(isGlobalAdmin: boolean): SidebarCategory[] {
  return categoryLinks.map((section) => ({
    ...section,
    children: section.children.filter((child) => !child.globalAdminOnly || isGlobalAdmin),
  }));
}

export function getCategorySectionForUser(
  sectionId: string,
  isGlobalAdmin: boolean,
): SidebarCategory | undefined {
  return getCategoryLinksForUser(isGlobalAdmin).find((section) => section.id === sectionId);
}