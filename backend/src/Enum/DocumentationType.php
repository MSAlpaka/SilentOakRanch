<?php

namespace App\Enum;

enum DocumentationType: string
{
    case BASIS = 'basis';
    case STANDARD = 'standard';
    case PREMIUM = 'premium';
}

