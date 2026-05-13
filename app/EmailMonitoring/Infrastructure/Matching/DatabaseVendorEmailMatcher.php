<?php

namespace App\EmailMonitoring\Infrastructure\Matching;

use App\EmailMonitoring\Contracts\VendorEmailMatcherInterface;
use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\EmailMonitoring\DTO\VendorEmailMatchResult;
use App\Models\VendorEmail;
use Illuminate\Support\Str;

class DatabaseVendorEmailMatcher implements VendorEmailMatcherInterface
{
    public function match(NormalizedInboundEmail $email, string $organizationId): VendorEmailMatchResult
    {
        $candidates = array_values(array_unique(array_filter([
            $email->fromEmail,
            ...$email->toRecipients,
            ...$email->ccRecipients,
        ])));

        if ($candidates === []) {
            return new VendorEmailMatchResult(null, null, 0.0, 'no_addresses', []);
        }

        $query = VendorEmail::query()
            ->where('organization_id', $organizationId)
            ->whereIn('email', $candidates)
            ->with('vendor');

        $hit = $query->first();

        if ($hit) {
            $confidence = $this->scoreHit($email, $hit->email);

            return new VendorEmailMatchResult(
                $hit->vendor_id,
                $hit->id,
                $confidence,
                'vendor_email_exact',
                ['matched_email' => $hit->email]
            );
        }

        $domainMatch = $this->matchByVendorWebsiteDomain($email, $organizationId);
        if ($domainMatch !== null) {
            return $domainMatch;
        }

        return new VendorEmailMatchResult(null, null, 0.0, 'no_match', []);
    }

    private function scoreHit(NormalizedInboundEmail $email, string $matchedEmail): float
    {
        if ($email->fromEmail !== null && strcasecmp($email->fromEmail, $matchedEmail) === 0) {
            return 1.0;
        }

        if (in_array(strtolower($matchedEmail), $email->toRecipients, true)) {
            return 0.92;
        }

        if (in_array(strtolower($matchedEmail), $email->ccRecipients, true)) {
            return 0.85;
        }

        return 0.75;
    }

    private function matchByVendorWebsiteDomain(NormalizedInboundEmail $email, string $organizationId): ?VendorEmailMatchResult
    {
        $fromDomain = $this->domainFromEmail($email->fromEmail);
        if ($fromDomain === null) {
            return null;
        }

        $vendorEmail = VendorEmail::query()
            ->where('organization_id', $organizationId)
            ->whereHas('vendor', function ($q) use ($fromDomain): void {
                $q->where('match_inbound_from_website_domain', true)
                    ->whereNotNull('website')
                    ->where('website', 'like', '%'.$fromDomain.'%');
            })
            ->first();

        if (! $vendorEmail) {
            return null;
        }

        return new VendorEmailMatchResult(
            $vendorEmail->vendor_id,
            $vendorEmail->id,
            0.55,
            'vendor_website_domain_heuristic',
            ['domain' => $fromDomain]
        );
    }

    private function domainFromEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        $host = Str::after($email, '@');

        return $host !== '' ? strtolower($host) : null;
    }
}
