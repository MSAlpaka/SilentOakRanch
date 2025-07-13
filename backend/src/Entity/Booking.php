<?php

namespace App\Entity;

use App\Enum\BookingStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\\Repository\\BookingRepository')]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: StallUnit::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StallUnit $stallUnit;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $endDate;

    #[ORM\ManyToOne(targetEntity: Horse::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Horse $horse = null;

    #[ORM\Column(type: 'string')]
    private string $user;

    #[ORM\Column(enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::PENDING;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStallUnit(): StallUnit
    {
        return $this->stallUnit;
    }

    public function setStallUnit(StallUnit $stallUnit): self
    {
        $this->stallUnit = $stallUnit;
        return $this;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getHorse(): ?Horse
    {
        return $this->horse;
    }

    public function setHorse(?Horse $horse): self
    {
        $this->horse = $horse;
        return $this;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setUser(string $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function setStatus(BookingStatus $status): self
    {
        $this->status = $status;
        return $this;
    }
}
