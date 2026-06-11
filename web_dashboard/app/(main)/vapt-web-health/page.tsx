import Link from "next/link";
import { ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function VaptWebHealthPage() {
  return (
    <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">VAPT &amp; Web Health</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Security and reliability checks for your web properties.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ShieldCheck className="h-5 w-5" />
              URL Checker
            </CardTitle>
            <CardDescription>
              Crawl a web page, collect discovered anchors, and measure link health with status and latency.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild>
              <Link href="/vapt-web-health/url-checker">Open URL Checker</Link>
            </Button>
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ShieldCheck className="h-5 w-5" />
              DNS Check
            </CardTitle>
            <CardDescription>
              Validate DNS records, security posture, latency, and policy checks with explanation and suggested actions.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild variant="secondary">
              <Link href="/vapt-web-health/dns-check">Open DNS Check</Link>
            </Button>
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ShieldCheck className="h-5 w-5" />
              Port Checker
            </CardTitle>
            <CardDescription>
              Scan commonly exposed TCP ports and custom port sets to identify public exposure risks.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild variant="secondary">
              <Link href="/vapt-web-health/port-checker">Open Port Checker</Link>
            </Button>
          </CardContent>
        </Card>

        <Card className="border-border/60">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <ShieldCheck className="h-5 w-5" />
              Website Speedtest
            </CardTitle>
            <CardDescription>
              Benchmark first-response and full-load timing signals for a public URL and keep reports in one place.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild variant="secondary">
              <Link href="/vapt-web-health/website-speedtest">Open Website Speedtest</Link>
            </Button>
          </CardContent>
        </Card>
      </div>

      <Card className="border-border/60 bg-muted/20">
        <CardHeader>
          <CardTitle className="text-base">Safety and rate-limit behavior</CardTitle>
          <CardDescription>
            URL Checker is tuned for safe health checks: max 5 parallel link requests, browser-like headers,
            and fallback logic for strict firewalls.
          </CardDescription>
        </CardHeader>
      </Card>
    </div>
  );
}
