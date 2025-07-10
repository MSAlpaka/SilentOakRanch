<?php

namespace App\Enum;

enum BookingType: string
{
    case SERVICE = 'service';
    case FACILITY = 'facility';
    case SPECIAL = 'special';
    case OTHER = 'other';
}

