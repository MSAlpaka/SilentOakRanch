<?php

namespace App\Entity;

use App\Enum\ContractStatus;
use App\Repository\ContractRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
class Contract
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Booking $booking;

    #[ORM\Column(length: 255)]
    private string $path = '';

    #[ORM\Column(length: 128)]
    private string $hash = '';

    #[ORM\Column(enumType: ContractStatus::class)]
    private ContractStatus $status = ContractStatus::QUEUED;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signedPath = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $signedHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $signedAt = null;

    #[ORM\Column(type: 'json')]
    private array $auditTrail = [];

    public function __construct()
    {
        $now = new DateTimeImmutable('now');
        $this->id = Uuid::v7();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->status = ContractStatus::QUEUED;
    }

    public function getId(): Uuid
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        $this->touch();

        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;
        $this->touch();

        return $this;
    }

    public function getStatus(): ContractStatus
    {
        return $this->status;
    }

    public function setStatus(ContractStatus $status): self
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getSignedPath(): ?string
    {
        return $this->signedPath;
    }

    public function setSignedPath(?string $signedPath): self
    {
        $this->signedPath = $signedPath;
        $this->touch();

        return $this;
    }

    public function getSignedHash(): ?string
    {
        return $this->signedHash;
    }

    public function setSignedHash(?string $signedHash): self
    {
        $this->signedHash = $signedHash;
        $this->touch();

        return $this;
    }

    public function getSignedAt(): ?DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(?DateTimeImmutable $signedAt): self
    {
        $this->signedAt = $signedAt;
        $this->touch();

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAuditTrail(): array
    {
        return $this->auditTrail;
    }

    /**
     * @param array<int, array<string, mixed>> $auditTrail
     */
    public function setAuditTrail(array $auditTrail): self
    {
        $this->auditTrail = $auditTrail;
        $this->touch();

        return $this;
    }

    public function appendAuditEntry(string $action, string $hash): void
    {
        $this->auditTrail[] = [
            'action' => $action,
            'hash' => $hash,
            'timestamp' => (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM),
            'contract_uuid' => (string) $this->id,
        ];
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable('now');
    }
}
