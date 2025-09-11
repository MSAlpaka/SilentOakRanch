<?php

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Enum\BookingType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column(enumType: BookingType::class)]
    private BookingType $type;

    #[ORM\Column(type: 'string')]
    private string $label;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateFrom;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTo = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isConfirmed = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\ManyToOne(targetEntity: Package::class)]
    private ?Package $package = null;

    #[ORM\ManyToMany(targetEntity: AddOn::class)]
    #[ORM\JoinTable(name: 'booking_add_on')]
    private Collection $addOns;

    public function __construct()
    {
        $this->addOns = new ArrayCollection();
    }

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

    public function getType(): BookingType
    {
        return $this->type;
    }

    public function setType(BookingType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function getDateFrom(): \DateTimeInterface
    {
        return $this->dateFrom;
    }

    public function setDateFrom(\DateTimeInterface $dateFrom): self
    {
        $this->dateFrom = $dateFrom;
        return $this;
    }

    public function getDateTo(): ?\DateTimeInterface
    {
        return $this->dateTo;
    }

    public function setDateTo(?\DateTimeInterface $dateTo): self
    {
        $this->dateTo = $dateTo;
        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function setIsConfirmed(bool $isConfirmed): self
    {
        $this->isConfirmed = $isConfirmed;
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

    public function getPackage(): ?Package
    {
        return $this->package;
    }

    public function setPackage(?Package $package): self
    {
        $this->package = $package;
        return $this;
    }

    /**
     * @return Collection<int, AddOn>
     */
    public function getAddOns(): Collection
    {
        return $this->addOns;
    }

    public function setAddOns(Collection $addOns): self
    {
        $this->addOns = $addOns;
        return $this;
    }

    public function addAddOn(AddOn $addOn): self
    {
        if (!$this->addOns->contains($addOn)) {
            $this->addOns->add($addOn);
        }
        return $this;
    }

    public function removeAddOn(AddOn $addOn): self
    {
        $this->addOns->removeElement($addOn);
        return $this;
    }
}
