<?php

namespace App\Entity;

use App\Enum\ScaleBookingStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\\Repository\\ScaleBookingRepository')]
class ScaleBooking
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $bookingDateTime;

    #[ORM\Column(type: 'string')]
    private string $customerName;

    #[ORM\Column(type: 'string')]
    private string $customerEmail;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: 'string')]
    private string $horseName;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $estimatedWeight = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $actualWeight = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(enumType: ScaleBookingStatus::class)]
    private ScaleBookingStatus $status = ScaleBookingStatus::PENDING;

    #[ORM\Column(type: 'guid')]
    private string $qrCode;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $redeemedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resultEmailSentAt = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function getBookingDateTime(): \DateTimeInterface
    {
        return $this->bookingDateTime;
    }

    public function setBookingDateTime(\DateTimeInterface $bookingDateTime): self
    {
        $this->bookingDateTime = $bookingDateTime;
        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;
        return $this;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(?string $customerPhone): self
    {
        $this->customerPhone = $customerPhone;
        return $this;
    }

    public function getHorseName(): string
    {
        return $this->horseName;
    }

    public function setHorseName(string $horseName): self
    {
        $this->horseName = $horseName;
        return $this;
    }

    public function getEstimatedWeight(): ?string
    {
        return $this->estimatedWeight;
    }

    public function setEstimatedWeight(?string $estimatedWeight): self
    {
        $this->estimatedWeight = $estimatedWeight;
        return $this;
    }

    public function getActualWeight(): ?string
    {
        return $this->actualWeight;
    }

    public function setActualWeight(?string $actualWeight): self
    {
        $this->actualWeight = $actualWeight;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getStatus(): ScaleBookingStatus
    {
        return $this->status;
    }

    public function setStatus(ScaleBookingStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getQrCode(): string
    {
        return $this->qrCode;
    }

    public function setQrCode(string $qrCode): self
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function getRedeemedAt(): ?\DateTimeInterface
    {
        return $this->redeemedAt;
    }

    public function setRedeemedAt(?\DateTimeInterface $redeemedAt): self
    {
        $this->redeemedAt = $redeemedAt;
        return $this;
    }

    public function getResultEmailSentAt(): ?\DateTimeInterface
    {
        return $this->resultEmailSentAt;
    }

    public function setResultEmailSentAt(?\DateTimeInterface $resultEmailSentAt): self
    {
        $this->resultEmailSentAt = $resultEmailSentAt;
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
}
