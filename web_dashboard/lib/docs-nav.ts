import { BookOpen, Building2, CircleHelp, Cog, Gauge, Plug, Rocket, Settings2, type LucideIcon } from "lucide-react";

export type DocPageDef = {
  href: string;
  label: string;
  icon: LucideIcon;
};

export const DOC_PAGES: DocPageDef[] = [
  { href: "/docs", label: "Overview", icon: BookOpen },
  { href: "/docs/getting-started", label: "Getting started", icon: Rocket },
  { href: "/docs/help-guide", label: "Help guide", icon: CircleHelp },
  { href: "/docs/configure", label: "Configure & use", icon: Settings2 },
  { href: "/docs/vendors-invoices", label: "Vendors & invoices", icon: Building2 },
  { href: "/docs/api", label: "API reference", icon: Plug },
  { href: "/docs/monitoring", label: "Site monitoring", icon: Gauge },
  { href: "/docs/operations", label: "Operations", icon: Cog },
];
