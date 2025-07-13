<?php

namespace App\Entity;

use App\Enum\SubscriptionInterval;
use App\Enum\SubscriptionType;
use App\Entity\StallUnit;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Horse::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Horse $horse = null;

    #[ORM\ManyToOne(targetEntity: StallUnit::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?StallUnit $stallUnit = null;

    #[ORM\Column(enumType: SubscriptionType::class)]
    private SubscriptionType $subscriptionType = SubscriptionType::USER;

    #[ORM\Column(type: 'string')]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $nextDue;

    #[ORM\Column(enumType: SubscriptionInterval::class)]
    private SubscriptionInterval $interval;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'boolean')]
    private bool $autoRenew = true;

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

    public function getHorse(): ?Horse
    {
        return $this->horse;
    }

    public function setHorse(?Horse $horse): self
    {
        $this->horse = $horse;
        return $this;
    }

    public function getStallUnit(): ?StallUnit
    {
        return $this->stallUnit;
    }

    public function setStallUnit(?StallUnit $stallUnit): self
    {
        $this->stallUnit = $stallUnit;
        return $this;
    }

    public function getSubscriptionType(): SubscriptionType
    {
        return $this->subscriptionType;
    }

    public function setSubscriptionType(SubscriptionType $subscriptionType): self
    {
        $this->subscriptionType = $subscriptionType;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
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

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;
        return $this;
    }

    public function getNextDue(): \DateTimeImmutable
    {
        return $this->nextDue;
    }

    public function setNextDue(\DateTimeImmutable $nextDue): self
    {
        $this->nextDue = $nextDue;
        return $this;
    }

    public function getInterval(): SubscriptionInterval
    {
        return $this->interval;
    }

    public function setInterval(SubscriptionInterval $interval): self
    {
        $this->interval = $interval;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): self
    {
        $this->autoRenew = $autoRenew;
        return $this;
    }
}
