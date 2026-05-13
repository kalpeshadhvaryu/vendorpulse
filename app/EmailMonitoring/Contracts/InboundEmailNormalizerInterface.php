<?php

namespace App\EmailMonitoring\Contracts;

use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\EmailMonitoring\DTO\RawEmailEnvelope;

interface InboundEmailNormalizerInterface
{
    public function normalize(RawEmailEnvelope $envelope): NormalizedInboundEmail;
}
