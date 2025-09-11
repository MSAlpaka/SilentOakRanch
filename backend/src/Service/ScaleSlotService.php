<?php

namespace App\Service;

use App\Repository\ScaleBookingRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

class ScaleSlotService
{
    public function __construct(private ScaleBookingRepository $bookingRepository)
    {
    }

    /**
     * Generates a list of available 30‑minute slots for the given day.
     *
     * @return DateTimeImmutable[]
     */
    public function getAvailableSlots(DateTimeInterface $day): array
    {
        $day = DateTimeImmutable::createFromInterface($day)->setTime(0, 0);
        $end = $day->modify('+1 day');
        $interval = new DateInterval('PT30M');

        $slots = [];
        for ($slot = $day; $slot < $end; $slot = $slot->add($interval)) {
            if ($this->isSlotAvailable($slot)) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    /**
     * Determines if the given slot is free of bookings.
     */
    public function isSlotAvailable(DateTimeInterface $slot): bool
    {
        return !$this->bookingRepository->existsForDateTime($slot);
    }
}
