<?php

namespace App\Tests;

use App\Controller\ScaleBookingController;
use App\Entity\Horse;
use App\Entity\ScaleBooking;
use App\Entity\User;
use App\Enum\ScaleBookingStatus;
use App\Enum\ScaleBookingType;
use App\Enum\UserRole;
use App\Service\ScaleBookingService;
use App\Service\ScaleSlotService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class ScaleBookingControllerTest extends TestCase
{
    private ScaleBookingController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ScaleBookingController();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    public function testSlotsReturns200(): void
    {
        $slotService = $this->createMock(ScaleSlotService::class);
        $slotService->method('getAvailableSlots')->willReturn([
            new \DateTimeImmutable('2024-01-01 10:00:00'),
        ]);

        $request = new Request(['day' => '2024-01-01']);
        $response = $this->controller->slots($request, $slotService);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCreateBookingForbiddenForNonOwner(): void
    {
        $owner = $this->createUser('owner@example.com');
        $horse = $this->createHorse($owner);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($horse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(Horse::class)
            ->willReturn($repository);

        $user = $this->createUser('other@example.com');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', null, false],
            ['ROLE_STAFF', null, false],
        ]);

        $bookingService = $this->createMock(ScaleBookingService::class);
        $bookingService->expects($this->never())->method('createBooking');

        $request = new Request([], [], [], [], [], [], json_encode([
            'horseId' => 123,
            'slot' => '2024-01-01T10:00:00+00:00',
            'type' => 'single',
        ]));

        $response = $this->controller->createBooking($request, $em, $bookingService, $security);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Forbidden',
        ], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testCreateBookingAllowedForHorseOwner(): void
    {
        $owner = $this->createUser('owner@example.com');
        $horse = $this->createHorse($owner);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(456)
            ->willReturn($horse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(Horse::class)
            ->willReturn($repository);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);
        $security->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', null, false],
            ['ROLE_STAFF', null, false],
        ]);

        $expectedSlot = new \DateTimeImmutable('2024-01-02T11:00:00+00:00');
        $booking = (new ScaleBooking())
            ->setId('booking-id')
            ->setHorse($horse)
            ->setOwner($owner)
            ->setSlot($expectedSlot)
            ->setBookingType(ScaleBookingType::SINGLE)
            ->setPrice('10.00')
            ->setStatus(ScaleBookingStatus::PENDING)
            ->setQrToken('qr-token')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $bookingService = $this->createMock(ScaleBookingService::class);
        $bookingService->expects($this->once())
            ->method('createBooking')
            ->with(
                $this->identicalTo($horse),
                $this->identicalTo($owner),
                $this->callback(fn ($slot) => $slot instanceof \DateTimeImmutable && $slot == $expectedSlot),
                ScaleBookingType::SINGLE,
            )
            ->willReturn($booking);

        $request = new Request([], [], [], [], [], [], json_encode([
            'horseId' => 456,
            'slot' => '2024-01-02T11:00:00+00:00',
            'type' => 'single',
        ]));

        $response = $this->controller->createBooking($request, $em, $bookingService, $security);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('booking-id', $data['id']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('10.00', $data['price']);
    }

    public function testCreateBookingAllowedForPrivilegedUser(): void
    {
        $owner = $this->createUser('owner@example.com');
        $horse = $this->createHorse($owner);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(789)
            ->willReturn($horse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(Horse::class)
            ->willReturn($repository);

        $admin = $this->createUser('admin@example.com', UserRole::ADMIN, ['ROLE_ADMIN']);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($admin);
        $security->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', null, true],
            ['ROLE_STAFF', null, false],
        ]);

        $expectedSlot = new \DateTimeImmutable('2024-01-03T12:00:00+00:00');
        $booking = (new ScaleBooking())
            ->setId('admin-booking')
            ->setHorse($horse)
            ->setOwner($admin)
            ->setSlot($expectedSlot)
            ->setBookingType(ScaleBookingType::SINGLE)
            ->setPrice('15.00')
            ->setStatus(ScaleBookingStatus::PENDING)
            ->setQrToken('qr-admin')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $bookingService = $this->createMock(ScaleBookingService::class);
        $bookingService->expects($this->once())
            ->method('createBooking')
            ->with(
                $this->identicalTo($horse),
                $this->identicalTo($admin),
                $this->callback(fn ($slot) => $slot instanceof \DateTimeImmutable && $slot == $expectedSlot),
                ScaleBookingType::SINGLE,
            )
            ->willReturn($booking);

        $request = new Request([], [], [], [], [], [], json_encode([
            'horseId' => 789,
            'slot' => '2024-01-03T12:00:00+00:00',
            'type' => 'single',
        ]));

        $response = $this->controller->createBooking($request, $em, $bookingService, $security);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('admin-booking', $data['id']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('15.00', $data['price']);
    }

    private function createUser(
        string $email = 'user@example.com',
        UserRole $role = UserRole::CUSTOMER,
        array $roles = []
    ): User {
        return (new User())
            ->setEmail($email)
            ->setPassword('password')
            ->setRoles($roles)
            ->setRole($role)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());
    }

    private function createHorse(User $owner): Horse
    {
        return (new Horse())
            ->setName('Star')
            ->setAge(7)
            ->setBreed('Arabian')
            ->setOwner($owner);
    }
}

