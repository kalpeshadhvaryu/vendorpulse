import type { ReactNode } from "react";
import { DocNav } from "@/components/docs/doc-nav";

export default function DocsLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-[calc(100vh-3.5rem)] flex-col lg:flex-row">
      <aside className="shrink-0 border-b border-border/60 bg-muted/20 lg:w-52 lg:border-b-0 lg:border-r">
        <DocNav />
      </aside>
      <div className="min-w-0 flex-1 border-t border-border/60 lg:border-l-0 lg:border-t-0">
        <div className="mx-auto max-w-3xl px-4 py-8 lg:px-8 lg:py-10">{children}</div>
      </div>
    </div>
  );
}
