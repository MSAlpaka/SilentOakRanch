<?php

namespace App\RanchBooking\Event;

use App\RanchBooking\Entity\Booking;
use Symfony\Contracts\EventDispatcher\Event;

class BookingStatusChangedEvent extends Event
{
    public function __construct(
        private readonly Booking $booking,
        private readonly ?string $previousStatus,
        private readonly string $newStatus
    ) {
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function getPreviousStatus(): ?string
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
