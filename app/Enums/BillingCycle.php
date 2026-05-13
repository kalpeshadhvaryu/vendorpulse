<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';
    case Biennial = 'biennial';
    case OneTime = 'one_time';
    case Custom = 'custom';
}
