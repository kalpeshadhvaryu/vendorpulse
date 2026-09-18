"use client";

import { cn } from "@/lib/utils";

type BrandLogoProps = {
  className?: string;
  /** Pixel size for the square mark (defaults work for sidebar/auth). */
  size?: number;
  priority?: boolean;
};

/** basePath from next.config.ts — required for static files behind /web_dashboard. */
const LOGO_SRC = `${process.env.NEXT_PUBLIC_BASE_PATH ?? "/web_dashboard"}/vendorpulse-logo.png`;

/**
 * Official VendorPulse mark. Uses a plain <img> so it works with Next basePath
 * (next/image optimizer breaks local public assets under /web_dashboard).
 */
export function BrandLogo({ className, size = 40, priority = false }: BrandLogoProps) {
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={LOGO_SRC}
      alt="VendorPulse"
      width={size}
      height={size}
      decoding="async"
      {...(priority ? { fetchPriority: "high" as const } : {})}
      className={cn("rounded-lg object-cover shadow-sm", className)}
    />
  );
}
