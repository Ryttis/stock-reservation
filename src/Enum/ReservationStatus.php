<?php

declare(strict_types=1);

namespace App\Enum;

enum ReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Cancelled = 'cancelled';
}
