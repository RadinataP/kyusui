<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case WAITING_VERIFICATION = 'WAITING_VERIFICATION';
    case PAID = 'PAID';
}
