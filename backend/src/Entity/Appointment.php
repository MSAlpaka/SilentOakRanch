<?php

namespace App\Entity;

use App\Enum\AppointmentStatus;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Horse::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Horse $horse;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: ServiceProvider::class)]
    private ?ServiceProvider $serviceProvider = null;

    #[ORM\ManyToOne(targetEntity: ServiceType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ServiceType $serviceType;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startTime;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $endTime;

    #[ORM\Column(enumType: AppointmentStatus::class)]
    private AppointmentStatus $status = AppointmentStatus::REQUESTED;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getServiceProvider(): ?ServiceProvider
    {
        return $this->serviceProvider;
    }

    public function setServiceProvider(?ServiceProvider $serviceProvider): self
    {
        $this->serviceProvider = $serviceProvider;
        return $this;
    }

    public function getServiceType(): ServiceType
    {
        return $this->serviceType;
    }

    public function setServiceType(ServiceType $serviceType): self
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getStartTime(): \DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): \DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getStatus(): AppointmentStatus
    {
        return $this->status;
    }

    public function setStatus(AppointmentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }
}
