<?php

namespace App\Enum;

enum PricingRuleType: string
{
    case SERVICE = 'service';
    case FACILITY = 'facility';
    case SPECIAL = 'special';
}

