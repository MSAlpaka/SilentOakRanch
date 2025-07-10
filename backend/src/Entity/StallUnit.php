<?php

namespace App\Entity;

use App\Enum\StallUnitType;
use App\Enum\StallUnitStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
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

    #[ORM\OneToOne(targetEntity: Horse::class, mappedBy: 'currentLocation')]
    private ?Horse $currentHorse = null;

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

    public function getCurrentHorse(): ?Horse
    {
        return $this->currentHorse;
    }

    public function setCurrentHorse(?Horse $horse): self
    {
        $this->currentHorse = $horse;
        return $this;
    }
}

