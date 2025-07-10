<?php

namespace App\Entity;

use App\Enum\TaskStatus;
use App\Enum\TaskOrigin;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dueAt = null;

    #[ORM\Column(enumType: TaskStatus::class)]
    private TaskStatus $status;

    #[ORM\Column(enumType: TaskOrigin::class)]
    private TaskOrigin $origin;

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $relatedId = null;

    #[ORM\ManyToOne(targetEntity: StallUnit::class)]
    private ?StallUnit $stallUnit = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDueAt(): ?\DateTimeInterface
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeInterface $dueAt): self
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getOrigin(): TaskOrigin
    {
        return $this->origin;
    }

    public function setOrigin(TaskOrigin $origin): self
    {
        $this->origin = $origin;
        return $this;
    }

    public function getRelatedId(): ?string
    {
        return $this->relatedId;
    }

    public function setRelatedId(?string $relatedId): self
    {
        $this->relatedId = $relatedId;
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
}
