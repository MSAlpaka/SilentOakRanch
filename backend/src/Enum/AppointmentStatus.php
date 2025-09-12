<?php

namespace App\Enum;

enum AppointmentStatus: string
{
    case REQUESTED = 'requested';
    case CONFIRMED = 'confirmed';
    case DONE = 'done';
    case CANCELED = 'canceled';
}
