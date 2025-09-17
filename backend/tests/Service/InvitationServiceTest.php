<?php

namespace App\Tests\Service;

use App\Entity\Invitation;
use App\Service\InvitationService;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InvitationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        unset($this->entityManager);
    }

    public function testSendInvitationPersistsAndSendsMail(): void
    {
        $mailToken = null;
        $mailService = $this->createMock(MailService::class);
        $mailService->expects($this->once())
            ->method('sendInvitation')
            ->with(
                'invitee@example.com',
                $this->callback(function (string $token) use (&$mailToken): bool {
                    $mailToken = $token;

                    return strlen($token) === 64;
                })
            );

        $service = new InvitationService($this->entityManager, $mailService);

        $token = $service->sendInvitation('invitee@example.com');

        $this->assertSame(64, strlen($token));
        $this->assertSame($mailToken, $token);

        $invitation = $this->entityManager->getRepository(Invitation::class)
            ->findOneBy(['email' => 'invitee@example.com']);

        $this->assertInstanceOf(Invitation::class, $invitation);
        $this->assertSame($token, $invitation->getToken());
        $this->assertFalse($invitation->isAccepted());
    }

    public function testAcceptInvitationMarksInvitationAsAccepted(): void
    {
        $invitation = (new Invitation())
            ->setEmail('invitee@example.com')
            ->setToken('token-123');

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $service = new InvitationService($this->entityManager, $this->createMock(MailService::class));

        $result = $service->acceptInvitation('token-123');

        $this->assertInstanceOf(Invitation::class, $result);
        $this->assertTrue($result->isAccepted());
    }

    public function testAcceptInvitationReturnsNullForUnknownToken(): void
    {
        $service = new InvitationService($this->entityManager, $this->createMock(MailService::class));

        $this->assertNull($service->acceptInvitation('missing-token'));
    }
}
