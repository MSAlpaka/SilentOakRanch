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

        $projectDir = sys_get_temp_dir();
        $service = new AgreementService($em, $repo, $projectDir);

        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tmp, '%PDF-1.4 test');
        $uploaded = new UploadedFile($tmp, 'contract.pdf', 'application/pdf', null, true);

        $agreement = $service->uploadContract($user, $uploaded, AgreementType::BOARDING_CONTRACT, 'v1');

        $expectedPath = $projectDir . '/public/agreements/10/5.pdf';
        $this->assertFileExists($expectedPath);
        $this->assertSame('agreements/10/5.pdf', $agreement->getFilePath());
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
