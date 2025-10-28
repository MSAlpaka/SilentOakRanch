<?php

namespace App\Tests\Service;

use App\Entity\Contract;
use App\Enum\SignatureValidationStatus;
use App\Service\SignatureValidator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('audit')]
class SignatureValidatorTest extends KernelTestCase
{
    public function testUnsignedContractReturnsUnsignedStatus(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var SignatureValidator $validator */
        $validator = $container->get(SignatureValidator::class);

        $contract = new Contract();
        $contract->setPath($this->createPdf('unsigned.pdf', '%PDF-1.4 unsigned')); 
        $contract->setHash(hash('sha256', file_get_contents($contract->getPath())));

        $result = $validator->validate($contract);

        self::assertSame(SignatureValidationStatus::UNSIGNED, $result->getStatus());
        self::assertSame($contract->getHash(), $result->getCalculatedHash());
    }

    public function testTamperedContractIsDetected(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var SignatureValidator $validator */
        $validator = $container->get(SignatureValidator::class);

        $originalPath = $this->createPdf('tampered-original.pdf', '%PDF-1.4 original');
        $signedPath = $this->createPdf('tampered-signed.pdf', '%PDF-1.4 signed ByteRange');

        $contract = new Contract();
        $contract->setPath($originalPath);
        $contract->setHash(hash('sha256', file_get_contents($originalPath)));
        $contract->setSignedPath($signedPath);
        $contract->setSignedHash(hash('sha256', 'incorrect'));

        $result = $validator->validate($contract);

        self::assertSame(SignatureValidationStatus::TAMPERED, $result->getStatus());
    }

    public function testValidSignedContract(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var SignatureValidator $validator */
        $validator = $container->get(SignatureValidator::class);

        $signedContent = '%PDF-1.7 Signed Document ByteRange /Sig <<PKCS7>>';
        $signedPath = $this->createPdf('valid-signed.pdf', $signedContent);

        $contract = new Contract();
        $contract->setPath($signedPath);
        $contract->setHash(hash('sha256', $signedContent));
        $contract->setSignedPath($signedPath);
        $contract->setSignedHash(hash('sha256', $signedContent));
        $contract->setSignedAt(new \DateTimeImmutable());

        $result = $validator->validate($contract);

        self::assertSame(SignatureValidationStatus::VALID, $result->getStatus());
        self::assertSame($contract->getSignedHash(), $result->getCalculatedHash());
    }

    private function createPdf(string $filename, string $content): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('sor-', true) . '-' . $filename;
        file_put_contents($path, $content);

        return $path;
    }
}
