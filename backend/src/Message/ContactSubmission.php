<?php

namespace App\Message;

class ContactSubmission
{
    public function __construct(
        private readonly string $name,
        private readonly string $email,
        private readonly string $message,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
