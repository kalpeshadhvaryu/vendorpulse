import type { ReactNode } from "react";

type Row = { name: string; description: ReactNode; example?: string };

export function ConfigTable({ rows }: { rows: Row[] }) {
  return (
    <div className="overflow-x-auto rounded-lg border border-border/60">
      <table className="w-full min-w-[32rem] border-collapse text-left text-xs">
        <thead>
          <tr className="border-b border-border/60 bg-muted/40">
            <th className="px-3 py-2 font-semibold text-foreground">Setting</th>
            <th className="px-3 py-2 font-semibold text-foreground">What it does</th>
            <th className="px-3 py-2 font-semibold text-foreground">Example</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.name} className="border-b border-border/40 last:border-0">
              <td className="whitespace-nowrap px-3 py-2 align-top font-mono text-[11px] text-foreground">{row.name}</td>
              <td className="px-3 py-2 align-top text-muted-foreground">{row.description}</td>
              <td className="px-3 py-2 align-top font-mono text-[10px] text-muted-foreground">{row.example ?? "—"}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
