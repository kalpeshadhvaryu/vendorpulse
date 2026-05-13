<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Churned = 'churned';
}
