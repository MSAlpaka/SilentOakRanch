<?php

namespace App\Tests;

use App\Command\ProcessSubscriptionsCommand;
use App\Entity\InvoiceItem;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionInterval;
use App\Enum\UserRole;
use App\Repository\InvoiceRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ProcessSubscriptionsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SubscriptionRepository $subscriptionRepository;
    private InvoiceRepository $invoiceRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->subscriptionRepository = $container->get(SubscriptionRepository::class);
        $this->invoiceRepository = $container->get(InvoiceRepository::class);

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

    public function testProcessSubscriptions(): void
    {
        $user = $this->createUser();

        $subscription = new Subscription();
        $subscription->setUser($user)
            ->setTitle('Boarding')
            ->setAmount('50.00')
            ->setStartsAt(new \DateTimeImmutable('2024-01-01'))
            ->setNextDue(new \DateTimeImmutable('2024-01-01'))
            ->setInterval(SubscriptionInterval::MONTHLY)
            ->setActive(true)
            ->setAutoRenew(true);
        $this->em->persist($subscription);
        $this->em->flush();

        $application = new Application(self::$kernel);
        $command = self::getContainer()->get(ProcessSubscriptionsCommand::class);
        $application->add($command);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('1 subscriptions processed', $output);

        $invoices = $this->invoiceRepository->findByUser($user->getEmail());
        $this->assertCount(1, $invoices);
        $invoice = $invoices[0];
        $items = $this->em->getRepository(InvoiceItem::class)->findBy(['invoice' => $invoice]);
        $this->assertCount(1, $items);
        $this->assertSame('Boarding', $items[0]->getLabel());
        $this->assertSame('50.00', $invoice->getTotal());

        $expectedNextDue = (new \DateTimeImmutable('2024-01-01'))->add(new \DateInterval('P1M'));
        $this->assertSame($expectedNextDue->format('Y-m-d'), $subscription->getNextDue()->format('Y-m-d'));
    }
}
