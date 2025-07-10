<?php

namespace App\Entity;

use App\Enum\Gender;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Horse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(enumType: Gender::class)]
    private Gender $gender;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $dateOfBirth;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: StallUnit::class, inversedBy: 'currentHorse')]
    private ?StallUnit $currentLocation = null;

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

    public function getGender(): Gender
    {
        return $this->gender;
    }

    public function setGender(Gender $gender): self
    {
        $this->gender = $gender;
        return $this;
    }

    public function getDateOfBirth(): \DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(\DateTimeInterface $dateOfBirth): self
    {
        $this->dateOfBirth = $dateOfBirth;
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

    public function getCurrentLocation(): ?StallUnit
    {
        return $this->currentLocation;
    }

    public function setCurrentLocation(?StallUnit $currentLocation): self
    {
        $this->currentLocation = $currentLocation;
        return $this;
    }
}

