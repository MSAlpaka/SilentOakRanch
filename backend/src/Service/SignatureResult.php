<?php

namespace App\Service;

use DateTimeImmutable;

class SignatureResult
{
    public function __construct(
        private readonly string $path,
        private readonly string $hash,
        private readonly DateTimeImmutable $signedAt
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getSignedAt(): DateTimeImmutable
    {
        return $this->signedAt;
    }
}
