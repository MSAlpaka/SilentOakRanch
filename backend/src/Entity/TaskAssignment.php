<?php

namespace App\Entity;

use App\Enum\TaskAssignmentStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TaskAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Task $task;

    #[ORM\Column(type: 'string')]
    private string $user;

    #[ORM\Column(enumType: TaskAssignmentStatus::class)]
    private TaskAssignmentStatus $status;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function setTask(Task $task): self
    {
        $this->task = $task;
        return $this;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setUser(string $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getStatus(): TaskAssignmentStatus
    {
        return $this->status;
    }

    public function setStatus(TaskAssignmentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }
}
