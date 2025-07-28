<?php

namespace App\Enum;

enum ScaleBookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PAID = 'paid';
    case REDEEMED = 'redeemed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
