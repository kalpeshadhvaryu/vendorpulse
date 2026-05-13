<?php

namespace App\Enums;

enum VendorType: string
{
    case Hosting = 'hosting';
    case Dns = 'dns';
    case Saas = 'saas';
    case Hardware = 'hardware';
    case Telecom = 'telecom';
    case Utility = 'utility';
    case Other = 'other';
}
