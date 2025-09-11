<?php

namespace App\Entity;

use App\Enum\ScaleBookingStatus;
use App\Enum\ScaleBookingType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\\Repository\\ScaleBookingRepository')]
#[ORM\HasLifecycleCallbacks]
class ScaleBooking
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Horse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Horse $horse;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $slot;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $weight = null;

    #[ORM\Column(enumType: ScaleBookingType::class)]
    private ScaleBookingType $bookingType;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(enumType: ScaleBookingStatus::class)]
    private ScaleBookingStatus $status;

    #[ORM\Column(type: 'string', unique: true)]
    private string $qrToken;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getHorse(): Horse
    {
        return $this->horse;
    }

    public function setHorse(Horse $horse): self
    {
        $this->horse = $horse;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getSlot(): \DateTimeInterface
    {
        return $this->slot;
    }

    public function setSlot(\DateTimeInterface $slot): self
    {
        $this->slot = $slot;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function getBookingType(): ScaleBookingType
    {
        return $this->bookingType;
    }

    public function setBookingType(ScaleBookingType $bookingType): self
    {
        $this->bookingType = $bookingType;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getStatus(): ScaleBookingStatus
    {
        return $this->status;
    }

    public function setStatus(ScaleBookingStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getQrToken(): string
    {
        return $this->qrToken;
    }

    public function setQrToken(string $qrToken): self
    {
        $this->qrToken = $qrToken;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
