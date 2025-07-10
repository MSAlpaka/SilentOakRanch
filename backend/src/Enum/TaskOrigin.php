<?php

namespace App\Enum;

enum TaskOrigin: string
{
    case MANUAL = 'manual';
    case SERVICE_BOOKING = 'serviceBooking';
    case SPECIAL_BOOKING = 'specialBooking';
}
