<?php

namespace App\Entity;

use App\Enum\DocumentationType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\\Repository\\DocumentationRepository')]
#[ORM\HasLifecycleCallbacks]
class Documentation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Booking $booking;

    #[ORM\Column(enumType: DocumentationType::class)]
    private DocumentationType $type;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $photos = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $videos = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metrics = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function setBooking(Booking $booking): self
    {
        $this->booking = $booking;
        return $this;
    }

    public function getType(): DocumentationType
    {
        return $this->type;
    }

    public function setType(DocumentationType $type): self
    {
        $this->type = $type;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getPhotos(): ?array
    {
        return $this->photos;
    }

    public function setPhotos(?array $photos): self
    {
        $this->photos = $photos;
        return $this;
    }

    public function getVideos(): ?array
    {
        return $this->videos;
    }

    public function setVideos(?array $videos): self
    {
        $this->videos = $videos;
        return $this;
    }

    public function getMetrics(): ?array
    {
        return $this->metrics;
    }

    public function setMetrics(?array $metrics): self
    {
        $this->metrics = $metrics;
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

