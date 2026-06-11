import Link from "next/link";
import { Megaphone } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function MarketingSeoPage() {
  return (
    <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Marketing &amp; SEO</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Content and technical SEO tools for improving search visibility and page quality.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Megaphone className="h-5 w-5" />
            On-Page SEO Scorecard
          </CardTitle>
          <CardDescription>
            Audit metadata, heading structure, image alt coverage, and Open Graph readiness for a target page.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button asChild>
            <Link href="/marketing-seo/on-page-seo-scorecard">Open scorecard</Link>
          </Button>
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Megaphone className="h-5 w-5" />
            Social Account Mapping
          </CardTitle>
          <CardDescription>
            This section is being redesigned and is temporarily unavailable.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button type="button" variant="outline" disabled>
            Coming soon
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
