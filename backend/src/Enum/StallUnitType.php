<?php

namespace App\Enum;

enum StallUnitType: string
{
    case BOX = 'box';
    case PADDOCK = 'paddock';
    case PASTURE = 'pasture';
    case OPEN_STABLE = 'open_stable';
}

