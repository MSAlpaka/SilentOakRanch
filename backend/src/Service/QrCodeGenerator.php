<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;

class QrCodeGenerator
{
    public function generate(string $data): string
    {
        $result = Builder::create()
            ->data($data)
            ->build();

        return $result->getString();
    }
}
