<?php

namespace App\Message;

class WpPaymentConfirmed
{
    public function __construct(
        private readonly int $bookingId,
        private readonly string $status,
        private readonly ?string $paymentReference,
        private readonly ?string $amount,
        private readonly array $payload
    ) {
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
