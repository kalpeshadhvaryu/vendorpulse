"use client";

import { useEffect, useState } from "react";
import { useTheme } from "next-themes";
import { cn } from "@/lib/utils";

type BrandLogoProps = {
  className?: string;
  /** Height in pixels; width follows the natural logo aspect ratio. */
  size?: number;
  priority?: boolean;
  /**
   * `light` — light artwork (dark surfaces).
   * `original` — original dark artwork (light surfaces).
   * `auto` — switches with theme: light logo in dark mode, original in light mode.
   */
  variant?: "light" | "original" | "auto";
};

const BASE = process.env.NEXT_PUBLIC_BASE_PATH ?? "/web_dashboard";
const LOGO_LIGHT = `${BASE}/vendorpulse-logo.png`;
const LOGO_ORIGINAL = `${BASE}/vendorpulse-logo-original.png`;

/** Light asset 500×373; original asset 150×119 (same aspect ≈ 1.34). */
const ASPECT = 500 / 373;

/**
 * Official VendorPulse mark. Uses a plain <img> so it works with Next basePath.
 */
export function BrandLogo({
  className,
  size = 40,
  priority = false,
  variant = "light",
}: BrandLogoProps) {
  const { resolvedTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const resolvedVariant =
    variant === "auto"
      ? mounted && resolvedTheme === "dark"
        ? "light"
        : "original"
      : variant;

  const height = size;
  const width = Math.round(size * ASPECT);
  const src = resolvedVariant === "original" ? LOGO_ORIGINAL : LOGO_LIGHT;
  const isLightArt = resolvedVariant === "light";

  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={src}
      alt="VendorPulse"
      width={width}
      height={height}
      decoding="async"
      {...(priority ? { fetchPriority: "high" as const } : {})}
      className={cn(
        "block object-contain bg-transparent",
        // Only add a plate when light art is forced onto a light surface (not auto).
        isLightArt && variant !== "auto" ? "bg-neutral-950/95 p-1 rounded-md dark:bg-transparent dark:p-0 dark:rounded-none" : null,
        className,
      )}
      style={{ width, height }}
    />
  );
}
