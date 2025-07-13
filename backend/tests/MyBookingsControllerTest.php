<?php

namespace App\Tests;

use App\Controller\MyBookingsController;
use App\Entity\Booking;
use App\Entity\Horse;
use App\Entity\StallUnit;
use App\Entity\User;
use App\Enum\BookingType;
use App\Enum\Gender;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Enum\UserRole;
use App\Repository\BookingRepository;
use App\Repository\StallUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;

class MyBookingsControllerTest extends KernelTestCase
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

    public function testMyBookings(): void
    {
        $stall = $this->createStallUnit();
        $user = $this->createUser();
        $horse = $this->createHorse($user, $stall);

        $booking = new Booking();
        $booking->setStallUnit($stall)
            ->setStartDate(new \DateTimeImmutable('2024-01-01'))
            ->setEndDate(new \DateTimeImmutable('2024-01-10'))
            ->setUser($user->getEmail())
            ->setHorse($horse)
            ->setType(BookingType::OTHER)
            ->setLabel('test')
            ->setDateFrom(new \DateTimeImmutable('2024-01-01'))
            ->setDateTo(new \DateTimeImmutable('2024-01-10'));
        $this->em->persist($booking);
        $this->em->flush();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $controller = static::getContainer()->get(MyBookingsController::class);

        $response = $controller->__invoke($this->bookingRepository, $security);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data);
        $this->assertSame('Box 1', $data[0]['stallUnit']['label']);
        $this->assertSame('PENDING', $data[0]['status']);
    }
}

