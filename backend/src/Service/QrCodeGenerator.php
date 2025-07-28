<?php

namespace App\Service;

use Endroid\QrCode\Builder\BuilderInterface;

class QrCodeGenerator
{
    public function __construct(private readonly BuilderInterface $builder)
    {
    }

    public function generate(string $data): string
    {
        $result = $this->builder
            ->data($data)
            ->build();

        return $result->getString();
    }
}
