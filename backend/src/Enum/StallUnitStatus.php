<?php

namespace App\Enum;

enum StallUnitStatus: string
{
    case FREE = 'free';
    case OCCUPIED = 'occupied';
    case MAINTENANCE = 'maintenance';
}

