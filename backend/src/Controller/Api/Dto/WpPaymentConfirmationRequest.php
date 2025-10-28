<?php

namespace App\Controller\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class WpPaymentConfirmationRequest
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $bookingId;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'getAllowedStatuses'])]
    private string $status;

    #[Assert\Length(max: 191)]
    private ?string $paymentReference;

    private ?string $amount;

    private array $rawPayload;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $self = new self();
        $self->bookingId = isset($payload['booking_id']) ? (int) $payload['booking_id'] : 0;
        $self->status = (string) ($payload['status'] ?? '');
        $self->paymentReference = isset($payload['payment_reference']) ? (string) $payload['payment_reference'] : null;
        $self->amount = isset($payload['amount']) ? (string) $payload['amount'] : null;
        $self->rawPayload = $payload;

        return $self;
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
        if ($this->amount === null || $this->amount === '') {
            return null;
        }

        if (!is_numeric($this->amount)) {
            throw new \InvalidArgumentException('Amount must be numeric.');
        }

        return number_format((float) $this->amount, 2, '.', '');
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    /**
     * @return list<string>
     */
    public static function getAllowedStatuses(): array
    {
        return ['paid', 'confirmed', 'completed'];
    }
}
