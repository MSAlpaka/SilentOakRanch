<?php

namespace App\Tests;

use App\Controller\Api\InvoiceController;
use App\Entity\Invoice;
use App\Entity\User;
use App\Enum\InvoiceStatus;
use App\Enum\UserRole;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

class InvoiceControllerTest extends KernelTestCase
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

    private function createUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('pw');
        $user->setRoles([]);
        $user->setRole(UserRole::CUSTOMER);
        $user->setFirstName('A');
        $user->setLastName('B');
        $user->setActive(true);
        $user->setCreatedAt(new \DateTimeImmutable());
        return $user;
    }

    public function testDownloadInvoicePdf(): void
    {
        $user = $this->createUser();

        $pdfPath = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($pdfPath, '%PDF-1.4');

        $invoice = new Invoice();
        $invoice->setUser($user);
        $invoice->setAmount('50.00');
        $invoice->setCurrency('USD');
        $invoice->setStatus(InvoiceStatus::SENT);
        $invoice->setCreatedAt(new \DateTimeImmutable());
        $invoice->setUpdatedAt(new \DateTimeImmutable());
        $invoice->setPdfPath($pdfPath);

        $this->em->persist($user);
        $this->em->persist($invoice);
        $this->em->flush();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        $controller = static::getContainer()->get(InvoiceController::class);
        $response = $controller->download($invoice->getId(), $this->invoiceRepository, $security);

        $response->prepare(new Request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function testGetInvoiceMetadata(): void
    {
        $user = $this->createUser();

        $invoice = new Invoice();
        $invoice->setUser($user);
        $invoice->setAmount('75.00');
        $invoice->setCurrency('USD');
        $invoice->setStatus(InvoiceStatus::PAID);
        $createdAt = new \DateTimeImmutable('2024-05-01T12:00:00Z');
        $invoice->setCreatedAt($createdAt);
        $invoice->setUpdatedAt($createdAt);

        $this->em->persist($user);
        $this->em->persist($invoice);
        $this->em->flush();

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        $controller = static::getContainer()->get(InvoiceController::class);
        $response = $controller->meta($invoice->getId(), $this->invoiceRepository, $security);

        $response->prepare(new Request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('content-type'));

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($invoice->getId(), $data['id']);
        $this->assertSame($invoice->getAmount(), $data['amount']);
        $this->assertSame($invoice->getStatus()->name, $data['status']);
        $this->assertSame($invoice->getCreatedAt()->format('c'), $data['createdAt']);
        $this->assertSame('/api/invoices/' . $invoice->getId(), $data['downloadUrl']);
    }
}
