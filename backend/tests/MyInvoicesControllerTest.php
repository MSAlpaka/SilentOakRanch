<?php

namespace App\Tests;

use App\Controller\MyInvoicesController;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\UserRole;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;

class MyInvoicesControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private InvoiceRepository $invoiceRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
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
        return $user;
    }

    public function testMyInvoices(): void
    {
        $user = $this->createUser();

        $invoice = new Invoice();
        $invoice->setUser($user->getEmail());
        $invoice->setNumber('INV-001');
        $invoice->setPeriod('2024-01');
        $invoice->setCreatedAt(new \DateTimeImmutable());
        $invoice->setStatus(InvoiceStatus::OPEN);
        $invoice->setTotal('100.00');
        $this->em->persist($invoice);
        $this->em->flush();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $controller = static::getContainer()->get(MyInvoicesController::class);

        $response = $controller->__invoke($this->invoiceRepository, $security);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data);
        $this->assertSame('INV-001', $data[0]['number']);
        $this->assertSame('OPEN', $data[0]['status']);
        $this->assertSame('/storage/invoices/INV-001.pdf', $data[0]['downloadUrl']);
    }
}
