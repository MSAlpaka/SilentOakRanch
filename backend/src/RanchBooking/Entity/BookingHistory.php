<?php

namespace App\RanchBooking\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'ranch_booking_history')]
class BookingHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'uuid')]
    private Uuid $bookingUuid;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $changedAt;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $oldStatus = null;

    #[ORM\Column(length: 32)]
    private string $newStatus;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $changedBy = null;

    public function __construct()
    {
        $this->changedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;

        return $this;
    }

    public function getBookingUuid(): Uuid
    {
        return $this->bookingUuid;
    }

    public function setBookingUuid(Uuid $bookingUuid): self
    {
        $this->bookingUuid = $bookingUuid;

        return $this;
    }

    public function getChangedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(DateTimeImmutable $changedAt): self
    {
        $this->changedAt = $changedAt->setTimezone(new DateTimeZone('UTC'));

        return $this;
    }

    public function getOldStatus(): ?string
    {
        return $this->oldStatus;
    }

    public function setOldStatus(?string $oldStatus): self
    {
        $this->oldStatus = $oldStatus;

        return $this;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }

    public function setNewStatus(string $newStatus): self
    {
        $this->newStatus = $newStatus;

        return $this;
    }

    public function getChangedBy(): ?string
    {
        return $this->changedBy;
    }

    public function setChangedBy(?string $changedBy): self
    {
        $this->changedBy = $changedBy;

        return $this;
    }
}
