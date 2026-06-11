import Link from "next/link";
import { getCategorySectionForUser } from "@/lib/navigation/main-menu";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";

export function MainMenuSectionCards({
  sectionId,
  statsByHref,
  isGlobalAdmin = false,
}: {
  sectionId: string;
  statsByHref?: Record<string, string>;
  isGlobalAdmin?: boolean;
}) {
  const section = getCategorySectionForUser(sectionId, isGlobalAdmin);

  if (!section) {
    return null;
  }

  const availableCount = section.children.filter((child) => child.href && !child.placeholder).length;

  return (
    <Card className="border-border/60">
      <CardHeader>
        <CardTitle className="text-base">{section.label} menus</CardTitle>
        <CardDescription>
          {availableCount}/{section.children.length} submenu pages are available.
        </CardDescription>
      </CardHeader>
      <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {section.children.map((child) => {
          const ChildIcon = child.icon;

          if (!child.href || child.placeholder) {
            return (
              <Card key={`${section.id}-${child.label}`} className="border-border/60 bg-muted/20">
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm">
                    <span className="flex items-center gap-2 text-muted-foreground">
                      <ChildIcon className="h-4 w-4" />
                      {child.label}
                    </span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <span className="inline-flex rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                    Soon
                  </span>
                </CardContent>
              </Card>
            );
          }

          const statLabel = statsByHref?.[child.href] ?? "Dedicated page";

          return (
            <Link key={`${section.id}-${child.href}`} href={child.href}>
              <Card className={cn("border-border/60 bg-background/80 transition-colors", "hover:bg-muted/30") }>
                <CardHeader className="pb-2">
                  <CardTitle className="text-sm">
                    <span className="flex items-center gap-2">
                      <ChildIcon className="h-4 w-4 text-primary" />
                      {child.label}
                    </span>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="text-xs text-muted-foreground">{statLabel}</p>
                </CardContent>
              </Card>
            </Link>
          );
        })}
      </CardContent>
    </Card>
  );
}