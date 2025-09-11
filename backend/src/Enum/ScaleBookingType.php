<?php

namespace App\Enum;

enum ScaleBookingType: string
{
    case SINGLE = 'single';
    case PACKAGE = 'package';
    case PREMIUM = 'premium';
    case DYNAMIC = 'dynamic';
}
