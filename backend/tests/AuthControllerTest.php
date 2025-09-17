<?php

namespace App\Tests;

use App\Controller\AuthController;
use App\Entity\Invitation;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\InvitationService;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private TranslatorInterface $translator;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->container = $container;
        $this->em = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->translator = $container->get(TranslatorInterface::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testRegisterCreatesUserWithRequiredFields(): void
    {
        $controller = $this->createController();

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf(User::class))
            ->willReturn('jwt-token');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'john@example.com',
            'password' => 'secret123',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]));

        $response = $controller->register($request, $this->em, $this->passwordHasher, $jwtManager);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('jwt-token', $data['token']);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'john@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());
        $this->assertTrue($user->isActive());
        $this->assertSame(UserRole::CUSTOMER, $user->getRole());
        $this->assertContains('ROLE_CUSTOMER', $user->getRoles());
        $this->assertInstanceOf(\DateTimeInterface::class, $user->getCreatedAt());
        $this->assertTrue($this->passwordHasher->isPasswordValid($user, 'secret123'));
    }

    public function testRegisterRejectsInvalidData(): void
    {
        $controller = $this->createController();
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'invalid',
            'password' => '',
        ]));

        $response = $controller->register($request, $this->em, $this->passwordHasher, $jwtManager);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, $this->em->getRepository(User::class)->count([]));
    }

    public function testInviteRequiresEmail(): void
    {
        $controller = $this->createController();
        $invitationService = $this->createMock(InvitationService::class);
        $invitationService->expects($this->never())
            ->method('sendInvitation');

        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $controller->invite($request, $invitationService);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testInviteSendsInvitation(): void
    {
        $controller = $this->createController();
        $invitationService = $this->createMock(InvitationService::class);
        $invitationService->expects($this->once())
            ->method('sendInvitation')
            ->with('invitee@example.com');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'invitee@example.com',
        ]));

        $response = $controller->invite($request, $invitationService);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(
            $this->translator->trans('Invitation sent', [], 'validators'),
            $data['message']
        );
    }

    public function testAcceptInviteUpdatesPasswordAndReturnsToken(): void
    {
        $controller = $this->createController();

        $user = new User();
        $user->setEmail('invitee@example.com');
        $user->setFirstName('Invited');
        $user->setLastName('User');
        $user->setRole(UserRole::CUSTOMER);
        $user->setRoles(['ROLE_CUSTOMER']);
        $user->setActive(false);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword($this->passwordHasher->hashPassword($user, 'old-pass'));
        $this->em->persist($user);

        $invitation = new Invitation();
        $invitation->setEmail('invitee@example.com');
        $invitation->setToken('token-123');
        $this->em->persist($invitation);
        $this->em->flush();

        $mailService = $this->createMock(MailService::class);
        $invitationService = new InvitationService($this->em, $mailService);

        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $jwtManager->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf(User::class))
            ->willReturn('new-token');

        $request = new Request([], [], [], [], [], [], json_encode(['password' => 'new-pass']));

        $response = $controller->acceptInvite('token-123', $request, $invitationService, $this->em, $this->passwordHasher, $jwtManager);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('new-token', $data['token']);

        $this->em->clear();

        $updatedUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'invitee@example.com']);
        $this->assertInstanceOf(User::class, $updatedUser);
        $this->assertTrue($updatedUser->isActive());
        $this->assertTrue($this->passwordHasher->isPasswordValid($updatedUser, 'new-pass'));

        $updatedInvitation = $this->em->getRepository(Invitation::class)->findOneBy(['token' => 'token-123']);
        $this->assertNotNull($updatedInvitation);
        $this->assertTrue($updatedInvitation->isAccepted());
    }

    public function testAcceptInviteRequiresPassword(): void
    {
        $controller = $this->createController();
        $mailService = $this->createMock(MailService::class);
        $invitationService = new InvitationService($this->em, $mailService);
        $jwtManager = $this->createMock(JWTTokenManagerInterface::class);

        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $controller->acceptInvite('missing', $request, $invitationService, $this->em, $this->passwordHasher, $jwtManager);

        $this->assertSame(400, $response->getStatusCode());
    }

    private function createController(): AuthController
    {
        $controller = new AuthController($this->translator);
        $controller->setContainer($this->container);

        return $controller;
    }
}
