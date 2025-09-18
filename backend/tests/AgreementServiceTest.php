<?php

namespace App\Tests;

use App\Entity\Agreement;
use App\Entity\User;
use App\Enum\AgreementType;
use App\Enum\AgreementStatus;
use App\Enum\UserRole;
use App\Repository\AgreementRepository;
use App\Service\AgreementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AgreementServiceTest extends TestCase
{
    public function testGiveConsentCreatesActiveAgreement(): void
    {
        $user = $this->createUser(1);

        $repo = $this->createMock(AgreementRepository::class);
        $repo->method('findActiveByUserAndType')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                $prop = new \ReflectionProperty($entity, 'id');
                $prop->setAccessible(true);
                $prop->setValue($entity, 1);
            });
        $em->expects($this->once())->method('flush');

        $service = new AgreementService($em, $repo, sys_get_temp_dir());

        $agreement = $service->giveConsent($user, AgreementType::AGB);

        $this->assertTrue($agreement->isConsentGiven());
        $this->assertEquals(AgreementStatus::ACTIVE, $agreement->getStatus());
    }

    public function testGiveConsentThrowsIfActiveExists(): void
    {
        $user = $this->createUser(1);

        $repo = $this->createMock(AgreementRepository::class);
        $repo->method('findActiveByUserAndType')->willReturn(new Agreement());

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new AgreementService($em, $repo, sys_get_temp_dir());

        $this->expectException(\RuntimeException::class);
        $service->giveConsent($user, AgreementType::AGB);
    }

    public function testUploadContractStoresPdf(): void
    {
        $user = $this->createUser(10);

        $repo = $this->createMock(AgreementRepository::class);
        $repo->method('findActiveByUserAndType')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                $prop = new \ReflectionProperty($entity, 'id');
                $prop->setAccessible(true);
                $prop->setValue($entity, 5);
            });
        $em->expects($this->exactly(2))->method('flush');

        $projectDir = sys_get_temp_dir() . '/agreement_service_' . uniqid('', true);
        if (!mkdir($projectDir) && !is_dir($projectDir)) {
            $this->fail(sprintf('Unable to create temporary project directory "%s"', $projectDir));
        }

        [$certificatePath, $privateKeyPath] = $this->createSigningMaterial();
        $service = new AgreementService($em, $repo, $projectDir, $certificatePath, $privateKeyPath);

        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tmp, '%PDF-1.4 test');
        $uploaded = new UploadedFile($tmp, 'contract.pdf', 'application/pdf', null, true);

        $expectedPath = null;
        $signaturePath = null;

        try {
            $agreement = $service->uploadContract($user, $uploaded, AgreementType::BOARDING_CONTRACT, 'v1');

            $expectedPath = $projectDir . '/var/agreements/10/5.pdf';
            $signaturePath = $expectedPath . '.sig';

            $this->assertFileExists($expectedPath);
            $this->assertFileExists($signaturePath);
            $this->assertSame('agreements/10/5.pdf', $agreement->getFilePath());

            $signatureJson = file_get_contents($signaturePath);
            $this->assertNotFalse($signatureJson, 'Signature file should be readable.');

            $payload = json_decode($signatureJson, true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Signature metadata must be valid JSON: ' . json_last_error_msg());
            $this->assertIsArray($payload);
            $this->assertArrayHasKey('signature', $payload);
            $this->assertArrayHasKey('certificate', $payload);
            $this->assertArrayHasKey('algorithm', $payload);
            $this->assertSame('sha256WithRSAEncryption', $payload['algorithm']);

            $signatureBinary = base64_decode($payload['signature'], true);
            $this->assertNotFalse($signatureBinary, 'Signature content must be valid base64.');

            $certificateBinary = base64_decode($payload['certificate'], true);
            $this->assertNotFalse($certificateBinary, 'Certificate content must be valid base64.');

            $publicKey = openssl_pkey_get_public($certificateBinary);
            $this->assertNotFalse($publicKey, 'Unable to read public key from certificate.');

            $pdfContents = file_get_contents($expectedPath);
            $this->assertNotFalse($pdfContents, 'Signed PDF should be readable.');

            $verification = openssl_verify($pdfContents, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
            $publicKey = null;

            $this->assertSame(1, $verification, 'Signature could not be verified with the provided certificate.');
        } finally {
            $this->cleanupSigningMaterial($certificatePath, $privateKeyPath, $expectedPath, $signaturePath, $tmp);
            @rmdir($projectDir . '/var/agreements/10');
            @rmdir($projectDir . '/var/agreements');
            @rmdir($projectDir . '/var');
            @rmdir($projectDir);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createSigningMaterial(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];

        $dn = [
            'countryName' => 'DE',
            'stateOrProvinceName' => 'Berlin',
            'localityName' => 'Berlin',
            'organizationName' => 'Silent Oak Ranch',
            'organizationalUnitName' => 'QA',
            'commonName' => 'testing.silentoakranch.local',
            'emailAddress' => 'qa@example.com',
        ];

        $privateKey = openssl_pkey_new($config);
        if ($privateKey === false) {
            $this->fail('Unable to create private key: ' . $this->getOpenSslErrorMessage());
        }

        $csr = openssl_csr_new($dn, $privateKey, $config);
        if ($csr === false) {
            $privateKey = null;
            $this->fail('Unable to create certificate request: ' . $this->getOpenSslErrorMessage());
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, $config);
        if ($certificate === false) {
            $privateKey = null;
            $this->fail('Unable to self-sign certificate: ' . $this->getOpenSslErrorMessage());
        }

        if (!openssl_x509_export($certificate, $certificateOut)) {
            $privateKey = null;
            $this->fail('Unable to export certificate: ' . $this->getOpenSslErrorMessage());
        }

        if (!openssl_pkey_export($privateKey, $privateKeyOut)) {
            $privateKey = null;
            $this->fail('Unable to export private key: ' . $this->getOpenSslErrorMessage());
        }

        $privateKey = null;

        $certificatePath = tempnam(sys_get_temp_dir(), 'agreement_cert_');
        $privateKeyPath = tempnam(sys_get_temp_dir(), 'agreement_key_');

        if ($certificatePath === false || $privateKeyPath === false) {
            $this->fail('Unable to allocate temporary files for signing configuration.');
        }

        if (file_put_contents($certificatePath, $certificateOut) === false) {
            $this->fail(sprintf('Unable to write certificate to "%s"', $certificatePath));
        }

        if (file_put_contents($privateKeyPath, $privateKeyOut) === false) {
            $this->fail(sprintf('Unable to write private key to "%s"', $privateKeyPath));
        }

        return [$certificatePath, $privateKeyPath];
    }

    private function cleanupSigningMaterial(?string ...$paths): void
    {
        foreach ($paths as $path) {
            if ($path === null) {
                continue;
            }

            @unlink($path);
        }
    }

    private function getOpenSslErrorMessage(): string
    {
        $messages = [];
        while (($message = openssl_error_string()) !== false) {
            $messages[] = $message;
        }

        return $messages === [] ? 'unknown error' : implode('; ', $messages);
    }

    private function createUser(int $id): User
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setPassword('pw')
            ->setRoles([])
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $prop = new \ReflectionProperty($user, 'id');
        $prop->setAccessible(true);
        $prop->setValue($user, $id);

        return $user;
    }
}
