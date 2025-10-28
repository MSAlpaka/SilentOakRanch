<?php

namespace App\Service;

use App\Enum\SignatureValidationStatus;

class SignatureValidationResult
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        private readonly SignatureValidationStatus $status,
        private readonly string $calculatedHash,
        private readonly ?string $expectedHash,
        private readonly array $details = []
    ) {
    }

    public function getStatus(): SignatureValidationStatus
    {
        return $this->status;
    }

    public function getCalculatedHash(): string
    {
        return $this->calculatedHash;
    }

    public function getExpectedHash(): ?string
    {
        return $this->expectedHash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
