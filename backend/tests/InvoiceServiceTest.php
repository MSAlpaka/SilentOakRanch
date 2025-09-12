<?php

namespace App\Tests;

use App\Entity\Horse;
use App\Entity\ScaleBooking;
use App\Entity\User;
use App\Enum\ScaleBookingStatus;
use App\Enum\ScaleBookingType;
use App\Enum\UserRole;
use App\Service\InvoiceService;
use App\Service\MailService;
use App\Service\PdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class InvoiceServiceTest extends TestCase
{
    public function testCreateFromStripePaymentGeneratesPdf(): void
    {
        $user = (new User())
            ->setEmail('test@example.com')
            ->setPassword('pw')
            ->setRoles([])
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $horse = (new Horse())
            ->setOwner($user)
            ->setName('Spirit')
            ->setAge(5)
            ->setBreed('Mustang');

        $booking = (new ScaleBooking())
            ->setId('sb1')
            ->setHorse($horse)
            ->setOwner($user)
            ->setSlot(new \DateTimeImmutable())
            ->setBookingType(ScaleBookingType::SINGLE)
            ->setPrice('123.45')
            ->setStatus(ScaleBookingStatus::PAID)
            ->setQrToken('token');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                if ($entity instanceof \App\Entity\Invoice) {
                    $prop = new \ReflectionProperty($entity, 'id');
                    $prop->setAccessible(true);
                    $prop->setValue($entity, 1);
                }
            });
        $em->expects($this->exactly(2))->method('flush');

        $pdfGenerator = $this->createMock(PdfGenerator::class);
        $pdfGenerator->method('generatePdf')->willReturn('%PDF-1.4 content');

        $mailService = $this->createMock(MailService::class);
        $mailService->expects($this->once())->method('sendInvoice');

        $projectDir = sys_get_temp_dir();
        $service = new InvoiceService($em, $pdfGenerator, $mailService, $projectDir);

        $invoice = $service->createFromStripePayment($booking, 'pi_test');

        $this->assertNotNull($invoice->getPdfPath());
        $this->assertFileExists($invoice->getPdfPath());
        $this->assertGreaterThan(0, filesize($invoice->getPdfPath()));
    }
}
