<?php

namespace App\Entity;

use App\Enum\BookingType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InvoiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(type: 'string')]
    private string $label;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(enumType: BookingType::class, nullable: true)]
    private ?BookingType $bookingType = null;

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $bookingId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;
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

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getBookingType(): ?BookingType
    {
        return $this->bookingType;
    }

    public function setBookingType(?BookingType $bookingType): self
    {
        $this->bookingType = $bookingType;
        return $this;
    }

    public function getBookingId(): ?string
    {
        return $this->bookingId;
    }

    public function setBookingId(?string $bookingId): self
    {
        $this->bookingId = $bookingId;
        return $this;
    }
}

