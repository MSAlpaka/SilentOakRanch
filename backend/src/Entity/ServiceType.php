<?php

namespace App\Entity;

use App\Enum\ServiceProviderType;
use App\Repository\ServiceTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceTypeRepository::class)]
class ServiceType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(enumType: ServiceProviderType::class)]
    private ServiceProviderType $providerType;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $defaultDurationMinutes;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $basePrice;

    #[ORM\Column(type: 'boolean')]
    private bool $taxable;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProviderType(): ServiceProviderType
    {
        return $this->providerType;
    }

    public function setProviderType(ServiceProviderType $providerType): self
    {
        $this->providerType = $providerType;
        return $this;
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

    public function getDefaultDurationMinutes(): int
    {
        return $this->defaultDurationMinutes;
    }

    public function setDefaultDurationMinutes(int $minutes): self
    {
        $this->defaultDurationMinutes = $minutes;
        return $this;
    }

    public function getBasePrice(): string
    {
        return $this->basePrice;
    }

    public function setBasePrice(string $basePrice): self
    {
        $this->basePrice = $basePrice;
        return $this;
    }

    public function isTaxable(): bool
    {
        return $this->taxable;
    }

    public function setTaxable(bool $taxable): self
    {
        $this->taxable = $taxable;
        return $this;
    }
}
