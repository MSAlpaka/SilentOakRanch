<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['timestamp', 'entity_type', 'entity_id'], name: 'idx_audit_timestamp_entity')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private Uuid $id;

    #[ORM\Column(name: 'entity_type', length: 191)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', length: 191)]
    private string $entityId;

    #[ORM\Column(length: 64)]
    private string $action;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $hash;

    #[ORM\Column(name: 'user_identifier', length: 191, nullable: true)]
    private ?string $userIdentifier;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $timestamp;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress;

    #[ORM\Column(type: 'json')]
    private array $meta;

    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        string $entityType,
        string $entityId,
        string $action,
        ?string $hash,
        ?string $userIdentifier,
        ?string $ipAddress,
        array $meta
    ) {
        $this->id = Uuid::v7();
        $this->timestamp = new DateTimeImmutable('now');
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->action = $action;
        $this->hash = $hash;
        $this->userIdentifier = $userIdentifier;
        $this->ipAddress = $ipAddress;
        $this->meta = $meta;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }
}
