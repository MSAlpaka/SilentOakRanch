<?php

namespace App\Tests;

use App\Controller\BookingController;
use App\Entity\Booking;
use App\Entity\Horse;
use App\Entity\StallUnit;
use App\Entity\User;
use App\Enum\Gender;
use App\Enum\BookingType;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Enum\UserRole;
use App\Repository\BookingRepository;
use App\Repository\StallUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;

class BookingControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BookingRepository $bookingRepository;
    private StallUnitRepository $stallUnitRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->bookingRepository = $container->get(BookingRepository::class);
        $this->stallUnitRepository = $container->get(StallUnitRepository::class);

        $tool = new SchemaTool($this->em);
        $tool->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('pw');
        $user->setRoles([]);
        $user->setRole(UserRole::CUSTOMER);
        $user->setFirstName('A');
        $user->setLastName('B');
        $user->setActive(true);
        $user->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function createStallUnit(): StallUnit
    {
        $stall = new StallUnit();
        $stall->setName('Box 1');
        $stall->setType(StallUnitType::BOX);
        $stall->setArea('A');
        $stall->setStatus(StallUnitStatus::FREE);
        $this->em->persist($stall);
        $this->em->flush();
        return $stall;
    }

    private function createHorse(User $owner, StallUnit $stall): Horse
    {
        $horse = new Horse();
        $horse->setName('Pony');
        $horse->setGender(Gender::MARE);
        $horse->setDateOfBirth(new \DateTimeImmutable('2020-01-01'));
        $horse->setOwner($owner);
        $horse->setCurrentLocation($stall);
        $this->em->persist($horse);
        $this->em->flush();
        return $horse;
    }

    public function testCreateBookingSuccess(): void
    {
        $stall = $this->createStallUnit();
        $user = $this->createUser();
        $horse = $this->createHorse($user, $stall);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $controller = static::getContainer()->get(BookingController::class);

        $request = new Request([], [], [], [], [], [], json_encode([
            'horseId' => $horse->getId(),
            'type' => 'service',
            'label' => 'Lesson',
            'dateFrom' => '2024-01-01T00:00:00Z'
        ]));

        $response = $controller->createHorseBooking($request, $this->em, $security, $this->stallUnitRepository);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('service', $data['type']);
        $this->assertSame('Lesson', $data['label']);
        $this->assertArrayHasKey('dateTo', $data);
        $this->assertNull($data['dateTo']);
        $this->assertNotNull($data['id']);
    }

    public function testOverlapRejected(): void
    {
        $stall = $this->createStallUnit();
        $user = $this->createUser();

        $booking = new Booking();
        $booking->setStallUnit($stall)
            ->setStartDate(new \DateTimeImmutable('2024-01-05'))
            ->setEndDate(new \DateTimeImmutable('2024-01-15'))
            ->setUser($user->getEmail())
            ->setType(BookingType::OTHER)
            ->setLabel('Overlap')
            ->setDateFrom(new \DateTimeImmutable('2024-01-05'))
            ->setDateTo(new \DateTimeImmutable('2024-01-15'));
        $this->em->persist($booking);
        $this->em->flush();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $controller = static::getContainer()->get(BookingController::class);
        $request = new Request([], [], [], [], [], [], json_encode([
            'stallUnitId' => $stall->getId(),
            'startDate' => '2024-01-10T00:00:00Z',
            'endDate' => '2024-01-20T00:00:00Z',
        ]));

        $response = $controller->__invoke($request, $this->stallUnitRepository, $this->bookingRepository, $this->em, $security);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateHorseBookingWithDateTo(): void
    {
        $stall = $this->createStallUnit();
        $user = $this->createUser();
        $horse = $this->createHorse($user, $stall);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $controller = static::getContainer()->get(BookingController::class);

        $request = new Request([], [], [], [], [], [], json_encode([
            'horseId' => $horse->getId(),
            'type' => 'service',
            'label' => 'Lesson',
            'dateFrom' => '2024-01-01T00:00:00Z',
            'dateTo' => '2024-01-03T00:00:00Z',
        ]));

        $response = $controller->createHorseBooking($request, $this->em, $security, $this->stallUnitRepository);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('2024-01-03T00:00:00+00:00', $data['dateTo']);
    }

    public function testHorseMustBelongToUser(): void
    {
        $stall = $this->createStallUnit();
        $owner = $this->createUser();
        $horse = $this->createHorse($owner, $stall);

        $otherUser = new User();
        $otherUser->setEmail('other@example.com');
        $otherUser->setPassword('pw');
        $otherUser->setRoles([]);
        $otherUser->setRole(UserRole::CUSTOMER);
        $otherUser->setFirstName('C');
        $otherUser->setLastName('D');
        $otherUser->setActive(true);
        $otherUser->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($otherUser);
        $this->em->flush();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($otherUser);

        $controller = static::getContainer()->get(BookingController::class);
        $request = new Request([], [], [], [], [], [], json_encode([
            'horseId' => $horse->getId(),
            'type' => 'service',
            'label' => 'Lesson',
            'dateFrom' => '2024-01-01T00:00:00Z'
        ]));

        $response = $controller->createHorseBooking($request, $this->em, $security, $this->stallUnitRepository);
        $this->assertSame(403, $response->getStatusCode());
    }
}
