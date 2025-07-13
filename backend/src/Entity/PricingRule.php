<?php

namespace App\Entity;

use App\Enum\PricingRuleType;
use App\Enum\PricingUnit;
use Doctrine\ORM\Mapping as ORM;

use App\Repository\PricingRuleRepository;

#[ORM\Entity(repositoryClass: PricingRuleRepository::class)]
class PricingRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(enumType: PricingRuleType::class)]
    private PricingRuleType $type;

    #[ORM\Column(type: 'string')]
    private string $target;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price;

    #[ORM\Column(enumType: PricingUnit::class)]
    private PricingUnit $unit;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $activeFrom = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $activeTo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean')]
    private bool $requiresSubscription;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): PricingRuleType
    {
        return $this->type;
    }

    public function setType(PricingRuleType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function setTarget(string $target): self
    {
        $this->target = $target;
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

    public function getUnit(): PricingUnit
    {
        return $this->unit;
    }

    public function setUnit(PricingUnit $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getActiveFrom(): ?\DateTimeInterface
    {
        return $this->activeFrom;
    }

    public function setActiveFrom(?\DateTimeInterface $activeFrom): self
    {
        $this->activeFrom = $activeFrom;
        return $this;
    }

    public function getActiveTo(): ?\DateTimeInterface
    {
        return $this->activeTo;
    }

    public function setActiveTo(?\DateTimeInterface $activeTo): self
    {
        $this->activeTo = $activeTo;
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

    public function isRequiresSubscription(): bool
    {
        return $this->requiresSubscription;
    }

    public function setRequiresSubscription(bool $requiresSubscription): self
    {
        $this->requiresSubscription = $requiresSubscription;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }
}

