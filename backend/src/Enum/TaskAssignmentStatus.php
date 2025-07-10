<?php

namespace App\Enum;

enum TaskAssignmentStatus: string
{
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
}
