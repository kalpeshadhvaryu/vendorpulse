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
            On-Page SEO
          </CardTitle>
          <CardDescription>
            Run quick SEO checks on one page or audit SEO signals across your domain.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button asChild>
            <Link href="/marketing-seo/on-page-seo-scorecard">Open On-Page SEO</Link>
          </Button>
        </CardContent>
      </Card>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Megaphone className="h-5 w-5" />
            Keywords
          </CardTitle>
          <CardDescription>
            Track priority keywords across Google rankings and AI answer visibility.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Button asChild variant="secondary">
            <Link href="/marketing-seo/keywords">Open Keywords</Link>
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
