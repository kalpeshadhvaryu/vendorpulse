<?php

namespace App\EmailMonitoring\Contracts;

use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\EmailMonitoring\DTO\VendorEmailMatchResult;

interface VendorEmailMatcherInterface
{
    public function match(NormalizedInboundEmail $email, string $organizationId): VendorEmailMatchResult;
}
