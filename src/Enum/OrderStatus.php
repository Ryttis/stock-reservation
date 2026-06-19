<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case PartiallyReserved = 'partially_reserved';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
}
