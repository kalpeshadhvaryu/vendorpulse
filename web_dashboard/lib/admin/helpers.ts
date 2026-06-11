import { toast } from "sonner";

import { cn } from "@/lib/utils";

export const adminSelectClass = cn(
  "flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm",
  "focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring",
);

export function normalizeSlug(value: string): string {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .replace(/-{2,}/g, "-");
}

export function generateTemporaryPassword(length = 14): string {
  const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*";
  const bytes = new Uint32Array(length);
  crypto.getRandomValues(bytes);

  return Array.from(bytes)
    .map((n) => chars[n % chars.length])
    .join("");
}

export async function copyToClipboard(value: string, description: string) {
  try {
    await navigator.clipboard.writeText(value);
    toast.success(`${description} copied`);
  } catch {
    toast.error("Could not copy to clipboard");
  }
}

export function confirmAction(message: string): boolean {
  return window.confirm(message);
}
