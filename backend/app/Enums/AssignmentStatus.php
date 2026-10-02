<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case ASSIGNED = 'ASSIGNED';
    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
}
