<?php

namespace App\Tests;

use App\Controller\DocumentationController;
use App\Entity\Booking;
use App\Entity\Documentation;
use App\Entity\User;
use App\Enum\DocumentationType;
use App\Enum\UserRole;
use App\Service\DocumentationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class DocumentationControllerSmokeTest extends TestCase
{
    private function createController(): DocumentationController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new DocumentationController($translator);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);
        return $controller;
    }

    private function createUser(): User
    {
        return (new User())
            ->setEmail('user@example.com')
            ->setPassword('x')
            ->setRoles([])
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('A')
            ->setLastName('B')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());
    }

    public function testListReturns200(): void
    {
        $controller = $this->createController();

        $booking = (new Booking())->setUser('user@example.com');
        $doc = (new Documentation())
            ->setBooking($booking)
            ->setType(DocumentationType::BASIS)
            ->setNotes('note')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $bookingRepo = $this->createMock(EntityRepository::class);
        $bookingRepo->method('find')->willReturn($booking);
        $docRepo = $this->createMock(EntityRepository::class);
        $docRepo->method('findBy')->willReturn([$doc]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Booking::class, $bookingRepo],
            [Documentation::class, $docRepo],
        ]);

        $user = $this->createUser();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        $response = $controller->list(1, $em, $security);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateReturns201(): void
    {
        $controller = $this->createController();

        $booking = (new Booking())->setUser('user@example.com');

        $bookingRepo = $this->createMock(EntityRepository::class);
        $bookingRepo->method('find')->willReturn($booking);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Booking::class, $bookingRepo],
        ]);

        $doc = (new Documentation())
            ->setBooking($booking)
            ->setType(DocumentationType::BASIS)
            ->setNotes('note')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $service = $this->createMock(DocumentationService::class);
        $service->method('createDocumentation')->willReturn($doc);

        $user = $this->createUser();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        $request = new Request([], [], [], [], [], [], json_encode([
            'type' => 'basis',
            'notes' => 'note',
        ]));

        $response = $controller->create(1, $request, $service, $em, $security);
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testExportReturns200(): void
    {
        $controller = $this->createController();

        $booking = (new Booking())->setUser('user@example.com');
        $doc = (new Documentation())
            ->setBooking($booking)
            ->setType(DocumentationType::PREMIUM)
            ->setNotes('note')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $docRepo = $this->createMock(EntityRepository::class);
        $docRepo->method('find')->willReturn($doc);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Documentation::class, $docRepo],
        ]);

        $service = $this->createMock(DocumentationService::class);
        $service->method('export')->willReturn('%PDF-1.4');

        $user = $this->createUser();
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        $response = $controller->export(1, $service, $em, $security);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
