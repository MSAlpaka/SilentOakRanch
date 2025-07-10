<?php

namespace App\Enum;

enum PricingUnit: string
{
    case PER_BOOKING = 'per_booking';
    case PER_DAY = 'per_day';
    case PER_HOUR = 'per_hour';
    case FLAT = 'flat';
}

