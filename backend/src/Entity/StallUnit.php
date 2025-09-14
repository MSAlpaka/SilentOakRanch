<?php

namespace App\Entity;

use App\Enum\StallUnitType;
use App\Enum\StallUnitStatus;
use App\Repository\StallUnitRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: StallUnitRepository::class)]
class StallUnit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(enumType: StallUnitType::class)]
    private StallUnitType $type;

    #[ORM\Column(type: 'string')]
    private string $area;

    #[ORM\Column(enumType: StallUnitStatus::class)]
    private StallUnitStatus $status;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $monthlyRent = '0.00';

    /**
     * @var Collection<int, Horse>
     */
    #[ORM\OneToMany(mappedBy: 'stallUnit', targetEntity: Horse::class)]
    private Collection $horses;

    public function __construct()
    {
        $this->horses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): StallUnitType
    {
        return $this->type;
    }

    public function setType(StallUnitType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getArea(): string
    {
        return $this->area;
    }

    public function setArea(string $area): self
    {
        $this->area = $area;
        return $this;
    }

    public function getStatus(): StallUnitStatus
    {
        return $this->status;
    }

    public function setStatus(StallUnitStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMonthlyRent(): string
    {
        return $this->monthlyRent;
    }

    public function setMonthlyRent(string $monthlyRent): self
    {
        $this->monthlyRent = $monthlyRent;
        return $this;
    }

    /**
     * @return Collection<int, Horse>
     */
    public function getHorses(): Collection
    {
        return $this->horses;
    }

    public function addHorse(Horse $horse): self
    {
        if (!$this->horses->contains($horse)) {
            $this->horses->add($horse);
            $horse->setStallUnit($this);
        }

        return $this;
    }

    public function removeHorse(Horse $horse): self
    {
        if ($this->horses->removeElement($horse) && $horse->getStallUnit() === $this) {
            $horse->setStallUnit(null);
        }

        return $this;
    }
}

