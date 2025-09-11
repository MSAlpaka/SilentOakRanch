<?php

namespace App\Service;

use App\Repository\BookingRepository;

class CalendarService
{
    public function __construct(private BookingRepository $bookingRepository)
    {
    }

    public function isRangeFree(\DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        return $this->bookingRepository->isDateRangeFree($start, $end);
    }
}
