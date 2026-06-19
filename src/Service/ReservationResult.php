<?php

declare(strict_types=1);

namespace App\Service;

use App\Allocation\DTO\AllocationResult;
use App\Entity\Reservation;

readonly class ReservationResult
{
    public function __construct(
        public Reservation $reservation,
        public AllocationResult $allocationResult,
    ) {}
}
