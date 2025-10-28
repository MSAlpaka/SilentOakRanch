<?php

namespace App\Controller\Api\Dto;

use App\Enum\BookingStatus;
use Symfony\Component\Validator\Constraints as Assert;

class WpBookingRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private string $uuid;

    #[Assert\NotBlank]
    #[Assert\Length(max: 191)]
    private string $resource;

    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    private string $slotStart;

    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    private string $slotEnd;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/')]
    private string $price;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'getAllowedStatuses'])]
    private string $status;

    #[Assert\Email(allowNull: true)]
    private ?string $email;

    #[Assert\Length(max: 255)]
    private ?string $name;

    #[Assert\Length(max: 255)]
    private ?string $horseName;

    #[Assert\Length(max: 64)]
    private ?string $phone;

    #[Assert\Positive(allowNull: true)]
    private ?int $stallUnitId;

    private array $rawPayload;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $self = new self();
        $self->uuid = (string) ($payload['uuid'] ?? '');
        $self->resource = (string) ($payload['resource'] ?? '');
        $self->slotStart = (string) ($payload['slot_start'] ?? '');
        $self->slotEnd = (string) ($payload['slot_end'] ?? '');
        $self->price = (string) ($payload['price'] ?? '');
        $self->status = (string) ($payload['status'] ?? '');
        $self->email = isset($payload['email']) ? (string) $payload['email'] : null;
        $self->name = isset($payload['name']) ? (string) $payload['name'] : null;
        $self->horseName = isset($payload['horse_name']) ? (string) $payload['horse_name'] : null;
        $self->phone = isset($payload['phone']) ? (string) $payload['phone'] : null;
        $self->stallUnitId = isset($payload['stall_unit_id']) ? (int) $payload['stall_unit_id'] : null;
        $self->rawPayload = $payload;

        return $self;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getSlotStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->slotStart);
    }

    public function getSlotEnd(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->slotEnd);
    }

    public function getPrice(): string
    {
        return number_format((float) $this->price, 2, '.', '');
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getHorseName(): ?string
    {
        return $this->horseName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getStallUnitId(): ?int
    {
        return $this->stallUnitId;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function getBookingStatus(): BookingStatus
    {
        return match ($this->status) {
            'cancelled' => BookingStatus::CANCELLED,
            'confirmed', 'paid', 'completed' => BookingStatus::CONFIRMED,
            default => BookingStatus::PENDING,
        };
    }

    /**
     * @return list<string>
     */
    public static function getAllowedStatuses(): array
    {
        return ['pending', 'paid', 'confirmed', 'completed', 'cancelled'];
    }
}
