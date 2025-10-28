<?php

namespace App\Message;

class ContractQueued
{
    public function __construct(
        private readonly int $bookingId,
        private readonly string $trigger
    ) {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }
}
