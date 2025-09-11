<?php

namespace App\Enum;

enum ScaleBookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PAID = 'paid';
    case COMPLETED = 'completed';
}
