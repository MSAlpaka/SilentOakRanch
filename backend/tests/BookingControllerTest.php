<?php

namespace App\Tests;

use App\Controller\BookingController;
use App\Entity\Booking;
use App\Entity\StallUnit;
use App\Entity\User;
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

    public function testCreateBookingSuccess(): void
    {
        $stall = $this->createStallUnit();
        $user = $this->createUser();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $controller = static::getContainer()->get(BookingController::class);

        $request = new Request([], [], [], [], [], [], json_encode([
            'stallUnitId' => $stall->getId(),
            'startDate' => '2024-01-01T00:00:00Z',
            'endDate' => '2024-01-10T00:00:00Z',
        ]));

        $response = $controller->__invoke($request, $this->stallUnitRepository, $this->bookingRepository, $this->em, $security);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('PENDING', $data['status']);
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
            ->setUser($user->getEmail());
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
}
