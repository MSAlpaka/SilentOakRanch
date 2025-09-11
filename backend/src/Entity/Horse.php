<?php

namespace App\Entity;

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

    #[ORM\Column(type: 'integer')]
    private int $age;

    #[ORM\Column(type: 'string')]
    private string $breed;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $specialNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $medicalHistory = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $medication = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

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

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): self
    {
        $this->age = $age;
        return $this;
    }

    public function getBreed(): string
    {
        return $this->breed;
    }

    public function setBreed(string $breed): self
    {
        $this->breed = $breed;
        return $this;
    }

    public function getSpecialNotes(): ?string
    {
        return $this->specialNotes;
    }

    public function setSpecialNotes(?string $specialNotes): self
    {
        $this->specialNotes = $specialNotes;
        return $this;
    }

    public function getMedicalHistory(): ?string
    {
        return $this->medicalHistory;
    }

    public function setMedicalHistory(?string $medicalHistory): self
    {
        $this->medicalHistory = $medicalHistory;
        return $this;
    }

    public function getMedication(): ?string
    {
        return $this->medication;
    }

    public function setMedication(?string $medication): self
    {
        $this->medication = $medication;
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

