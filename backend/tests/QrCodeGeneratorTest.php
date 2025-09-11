<?php

namespace App\Tests;

use App\Service\QrCodeGenerator;
use PHPUnit\Framework\TestCase;

class QrCodeGeneratorTest extends TestCase
{
    public function testGenerateProducesData(): void
    {
        $generator = new QrCodeGenerator();
        $token = bin2hex(random_bytes(8));

        $data = $generator->generate($token);

        self::assertNotEmpty($data);
        self::assertStringStartsWith("\x89PNG", $data);
    }
}
