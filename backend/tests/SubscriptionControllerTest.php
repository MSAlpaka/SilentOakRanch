<?php

namespace App\Tests;

use App\Controller\Api\SubscriptionController;
use App\Entity\Horse;
use App\Entity\StallUnit;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Gender;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Enum\SubscriptionInterval;
use App\Enum\SubscriptionType;
use App\Enum\UserRole;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class SubscriptionControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SubscriptionRepository $subscriptionRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->subscriptionRepository = $container->get(SubscriptionRepository::class);

        $tool = new SchemaTool($this->em);
        $tool->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('admin@example.com');
        $user->setPassword('pw');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setRole(UserRole::ADMIN);
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
        $stall->setCurrentHorse($horse);
        $this->em->persist($horse);
        $this->em->flush();
        return $horse;
    }

    public function testCreateSubscription(): void
    {
        $user = $this->createUser();
        $stall = $this->createStallUnit();
        $horse = $this->createHorse($user, $stall);

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request([], [], [], [], [], [], json_encode([
            'type' => 'stall',
            'title' => 'Boarding',
            'amount' => '100.00',
            'startsAt' => '2024-01-01T00:00:00Z',
            'interval' => 'monthly',
            'stallUnitId' => $stall->getId(),
        ]));

        $response = $controller->create($request, $this->em);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertNotNull($data['id']);
        $this->assertCount(1, $this->subscriptionRepository->findAll());
    }

    public function testCreateSubscriptionValidation(): void
    {
        $user = $this->createUser();
        $stall = $this->createStallUnit();
        $horse = $this->createHorse($user, $stall);

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request([], [], [], [], [], [], json_encode([
            'type' => 'stall',
            'title' => 'Boarding',
            'amount' => '100.00',
            'startsAt' => '2024-01-01T00:00:00Z',
            'interval' => 'monthly',
            'horseId' => $horse->getId(),
            'stallUnitId' => $stall->getId(),
        ]));

        $response = $controller->create($request, $this->em);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertCount(0, $this->subscriptionRepository->findAll());
    }

    public function testCreateSubscriptionStartDateAlias(): void
    {
        $user = $this->createUser();
        $stall = $this->createStallUnit();
        $this->createHorse($user, $stall);

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request([], [], [], [], [], [], json_encode([
            'type' => 'stall',
            'title' => 'Boarding',
            'amount' => '100.00',
            'startDate' => '2024-01-01T00:00:00Z',
            'interval' => 'monthly',
            'stallUnitId' => $stall->getId(),
        ]));

        $response = $controller->create($request, $this->em);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertCount(1, $this->subscriptionRepository->findAll());
    }

    public function testCreateSubscriptionInvalidAmount(): void
    {
        $stall = $this->createStallUnit();

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request([], [], [], [], [], [], json_encode([
            'type' => 'stall',
            'title' => 'Boarding',
            'amount' => '0',
            'startsAt' => '2024-01-01T00:00:00Z',
            'interval' => 'monthly',
            'stallUnitId' => $stall->getId(),
        ]));

        $response = $controller->create($request, $this->em);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertCount(0, $this->subscriptionRepository->findAll());
    }

    public function testCreateSubscriptionRequiresEndDate(): void
    {
        $stall = $this->createStallUnit();

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request([], [], [], [], [], [], json_encode([
            'type' => 'stall',
            'title' => 'Boarding',
            'amount' => '100.00',
            'startsAt' => '2024-01-01T00:00:00Z',
            'interval' => 'monthly',
            'stallUnitId' => $stall->getId(),
            'autoRenew' => false,
        ]));

        $response = $controller->create($request, $this->em);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertCount(0, $this->subscriptionRepository->findAll());
    }

    public function testListSubscriptions(): void
    {
        $user = $this->createUser();
        $stall = $this->createStallUnit();
        $this->createHorse($user, $stall);

        $subscription = new Subscription();
        $subscription->setUser($user)
            ->setStallUnit($stall)
            ->setSubscriptionType(SubscriptionType::STALL)
            ->setTitle('Boarding')
            ->setAmount('100.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(true)
            ->setAutoRenew(true);
        $this->em->persist($subscription);
        $this->em->flush();

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request();
        $response = $controller->list($request, $this->subscriptionRepository);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data);
        $this->assertSame('Boarding', $data[0]['title']);
        $this->assertSame($user->getId(), $data[0]['user']['id']);
        $this->assertSame($user->getFirstName().' '.$user->getLastName(), $data[0]['user']['name']);
        $this->assertSame($stall->getId(), $data[0]['stallUnit']['id']);
        $this->assertSame($stall->getName(), $data[0]['stallUnit']['name']);
    }

    public function testListSubscriptionsActiveOnlyFilter(): void
    {
        $user = $this->createUser();
        $stall = $this->createStallUnit();
        $this->createHorse($user, $stall);

        $active = new Subscription();
        $active->setUser($user)
            ->setStallUnit($stall)
            ->setSubscriptionType(SubscriptionType::STALL)
            ->setTitle('Active')
            ->setAmount('100.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(true)
            ->setAutoRenew(true);
        $this->em->persist($active);

        $inactive = new Subscription();
        $inactive->setUser($user)
            ->setStallUnit($stall)
            ->setSubscriptionType(SubscriptionType::STALL)
            ->setTitle('Inactive')
            ->setAmount('100.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(false)
            ->setAutoRenew(true);
        $this->em->persist($inactive);
        $this->em->flush();

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request(['activeOnly' => 'true']);
        $response = $controller->list($request, $this->subscriptionRepository);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data);
        $this->assertSame('Active', $data[0]['title']);
    }

    public function testListSubscriptionsTypeFilter(): void
    {
        $user = $this->createUser();
        $stall = $this->createStallUnit();
        $horse = $this->createHorse($user, $stall);

        $stallSub = new Subscription();
        $stallSub->setUser($user)
            ->setStallUnit($stall)
            ->setSubscriptionType(SubscriptionType::STALL)
            ->setTitle('Stall')
            ->setAmount('100.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(true)
            ->setAutoRenew(true);
        $this->em->persist($stallSub);

        $horseSub = new Subscription();
        $horseSub->setUser($user)
            ->setHorse($horse)
            ->setSubscriptionType(SubscriptionType::HORSE)
            ->setTitle('Horse')
            ->setAmount('50.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(true)
            ->setAutoRenew(true);
        $this->em->persist($horseSub);
        $this->em->flush();

        $controller = static::getContainer()->get(SubscriptionController::class);
        $request = new Request(['type' => 'horse']);
        $response = $controller->list($request, $this->subscriptionRepository);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data);
        $this->assertSame('Horse', $data[0]['title']);
    }
}
