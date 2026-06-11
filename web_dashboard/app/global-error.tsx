"use client";

import { useEffect } from "react";
import "./globals.css";

/**
 * Root-level error UI when the root layout fails. Must define its own <html> and <body>.
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <html lang="en">
      <body className="flex min-h-screen flex-col items-center justify-center gap-6 bg-background px-4 font-sans text-foreground antialiased">
        <div className="max-w-md text-center">
          <h1 className="text-lg font-semibold">VendorPulse couldn&apos;t load</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            {error.message || "A critical error occurred. Please refresh the page."}
          </p>
        </div>
        <button
          type="button"
          onClick={() => reset()}
          className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >
          Try again
        </button>
      </body>
    </html>
  );
}
