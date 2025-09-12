<?php

namespace App\Entity;

use App\Enum\AgreementStatus;
use App\Enum\AgreementType;
use App\Repository\AgreementRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AgreementRepository::class)]
class Agreement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(enumType: AgreementType::class)]
    private AgreementType $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $version;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: 'boolean')]
    private bool $consentGiven = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $consentAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $signedAt = null;

    #[ORM\Column(enumType: AgreementStatus::class)]
    private AgreementStatus $status;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): AgreementType
    {
        return $this->type;
    }

    public function setType(AgreementType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function isConsentGiven(): bool
    {
        return $this->consentGiven;
    }

    public function setConsentGiven(bool $consentGiven): self
    {
        $this->consentGiven = $consentGiven;
        return $this;
    }

    public function getConsentAt(): DateTimeImmutable
    {
        return $this->consentAt;
    }

    public function setConsentAt(DateTimeImmutable $consentAt): self
    {
        $this->consentAt = $consentAt;
        return $this;
    }

    public function getSignedAt(): ?DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(?DateTimeImmutable $signedAt): self
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getStatus(): AgreementStatus
    {
        return $this->status;
    }

    public function setStatus(AgreementStatus $status): self
    {
        $this->status = $status;
        return $this;
    }
}
