<?php

namespace App\Service;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeGenerator
{
    public function generate(string $data): string
    {
        $qrCode = new QrCode($data);

        $result = (new PngWriter())->write($qrCode);

        return $result->getString();
    }
}
