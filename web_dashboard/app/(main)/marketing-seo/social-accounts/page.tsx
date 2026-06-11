import Link from "next/link";
import { Construction, Globe2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function MarketingSeoSocialAccountsPage() {
  return (
    <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Social Account Mapping</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          This area is under development and the previous social mapping workflow has been removed for now.
        </p>
      </div>

      <Card className="border-border/60">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Construction className="h-5 w-5" />
            Coming soon
          </CardTitle>
          <CardDescription>
            Social account mapping is being redesigned to make the domain and SEO flow clearer.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4 text-sm text-muted-foreground">
          <p>
            For now, use Monitoring to manage domains and use the On-Page SEO Scorecard only for page audits.
          </p>
          <div className="flex flex-wrap gap-2">
            <Button asChild>
              <Link href="/marketing-seo/on-page-seo-scorecard">
                <Globe2 className="h-4 w-4" />
                Open SEO scorecard
              </Link>
            </Button>
            <Button asChild variant="outline">
              <Link href="/monitoring">Open Monitoring</Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

