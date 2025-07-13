<?php

namespace App\Tests;

use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionInterval;
use App\Enum\SubscriptionType;
use App\Enum\UserRole;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SubscriptionRepositoryTest extends KernelTestCase
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
        $user->setEmail('sub@example.com');
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

    public function testFindDueSubscriptions(): void
    {
        $user = $this->createUser();
        $now = new \DateTimeImmutable('2024-02-01');

        $due = new Subscription();
        $due->setUser($user)
            ->setSubscriptionType(SubscriptionType::USER)
            ->setTitle('Due')
            ->setAmount('10.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(true)
            ->setAutoRenew(true);
        $this->em->persist($due);

        $future = new Subscription();
        $future->setUser($user)
            ->setSubscriptionType(SubscriptionType::USER)
            ->setTitle('Future')
            ->setAmount('10.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-03-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(true)
            ->setAutoRenew(true);
        $this->em->persist($future);

        $inactive = new Subscription();
        $inactive->setUser($user)
            ->setSubscriptionType(SubscriptionType::USER)
            ->setTitle('Inactive')
            ->setAmount('10.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(false)
            ->setAutoRenew(true);
        $this->em->persist($inactive);

        $this->em->flush();

        $result = $this->subscriptionRepository->findDueSubscriptions($now);

        $this->assertCount(1, $result);
        $this->assertSame('Due', $result[0]->getTitle());
    }
}
