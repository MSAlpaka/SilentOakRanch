<?php

namespace App\RanchBooking\Entity;

use App\RanchBooking\Repository\BookingRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'ranch_booking')]
#[ORM\UniqueConstraint(name: 'uniq_ranch_booking_uuid', columns: ['id'])]
#[ORM\Index(name: 'idx_ranch_booking_resource_slot', columns: ['resource', 'slot_start', 'slot_end'])]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    public const RESOURCE_SOLEKAMMER = 'solekammer';
    public const RESOURCE_WAAGE = 'waage';
    public const RESOURCE_SCHMIED = 'schmied';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const SOURCE_WEBSITE = 'website';
    public const SOURCE_APP = 'app';
    public const SOURCE_MANUAL = 'manual';

    public const VALID_RESOURCES = [
        self::RESOURCE_SOLEKAMMER,
        self::RESOURCE_WAAGE,
        self::RESOURCE_SCHMIED,
    ];

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const VALID_SOURCES = [
        self::SOURCE_WEBSITE,
        self::SOURCE_APP,
        self::SOURCE_MANUAL,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private Uuid $id;

    #[ORM\Column(length: 32)]
    private string $resource;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'horse_name', length: 255, nullable: true)]
    private ?string $horseName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'slot_start')]
    private DateTimeImmutable $slotStart;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'slot_end')]
    private DateTimeImmutable $slotEnd;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(length: 32)]
    private string $status;

    #[ORM\Column(length: 32)]
    private string $source = self::SOURCE_WEBSITE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentRef = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $syncedFrom = null;

    /**
     * @var Collection<int, BookingHistory>
     */
    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: BookingHistory::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $history;

    public function __construct()
    {
        $this->history = new ArrayCollection();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->status = self::STATUS_PENDING;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function setResource(string $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name ?? '';

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getHorseName(): ?string
    {
        return $this->horseName;
    }

    public function setHorseName(?string $horseName): self
    {
        $this->horseName = $horseName;

        return $this;
    }

    public function getSlotStart(): DateTimeImmutable
    {
        return $this->slotStart;
    }

    public function setSlotStart(DateTimeImmutable $slotStart): self
    {
        $this->slotStart = $slotStart->setTimezone(new DateTimeZone('UTC'));

        return $this;
    }

    public function getSlotEnd(): DateTimeImmutable
    {
        return $this->slotEnd;
    }

    public function setSlotEnd(DateTimeImmutable $slotEnd): self
    {
        $this->slotEnd = $slotEnd->setTimezone(new DateTimeZone('UTC'));

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt->setTimezone(new DateTimeZone('UTC'));

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt->setTimezone(new DateTimeZone('UTC'));

        return $this;
    }

    public function getPaymentRef(): ?string
    {
        return $this->paymentRef;
    }

    public function setPaymentRef(?string $paymentRef): self
    {
        $this->paymentRef = $paymentRef;

        return $this;
    }

    public function getSyncedFrom(): ?string
    {
        return $this->syncedFrom;
    }

    public function setSyncedFrom(?string $syncedFrom): self
    {
        $this->syncedFrom = $syncedFrom;

        return $this;
    }

    /**
     * @return Collection<int, BookingHistory>
     */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(BookingHistory $history): self
    {
        if (!$this->history->contains($history)) {
            $this->history->add($history);
            $history->setBooking($this);
        }

        return $this;
    }

    public function removeHistory(BookingHistory $history): self
    {
        if ($this->history->removeElement($history)) {
            if ($history->getBooking() === $this) {
                $history->setBooking(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->createdAt = $this->createdAt ?? $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
