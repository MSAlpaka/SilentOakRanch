<?php

namespace App\Tests;

use App\Entity\Appointment;
use App\Entity\ServiceType;
use App\Enum\AppointmentStatus;
use App\Enum\ServiceProviderType;
use App\Service\AppointmentService;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AppointmentServiceTest extends TestCase
{
    public function testCompleteCreatesInvoiceWhenRequested(): void
    {
        $appointment = new Appointment();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService->expects($this->once())
            ->method('createForAppointment')
            ->with($appointment);

        $service = new AppointmentService($em, $invoiceService);
        $service->complete($appointment, true);

        $this->assertSame(AppointmentStatus::DONE, $appointment->getStatus());
    }

    public function testCompleteWithoutInvoice(): void
    {
        $appointment = new Appointment();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService->expects($this->never())->method('createForAppointment');

        $service = new AppointmentService($em, $invoiceService);
        $service->complete($appointment, false);

        $this->assertSame(AppointmentStatus::DONE, $appointment->getStatus());
    }

    public function testConfirmUpdatesStatus(): void
    {
        $appointment = new Appointment();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $invoiceService = $this->createStub(InvoiceService::class);

        $service = new AppointmentService($em, $invoiceService);
        $service->confirm($appointment);

        $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->getStatus());
    }

    public function testCalculatePriceAddsSurcharges(): void
    {
        $serviceType = (new ServiceType())
            ->setProviderType(ServiceProviderType::VET)
            ->setName('Checkup')
            ->setDefaultDurationMinutes(30)
            ->setBasePrice('50.00')
            ->setTaxable(false);

        $em = $this->createStub(EntityManagerInterface::class);
        $invoiceService = $this->createStub(InvoiceService::class);

        $service = new AppointmentService($em, $invoiceService);
        $price = $service->calculatePrice($serviceType, [10.0, 5.5]);

        $this->assertSame('65.50', $price);
    }
}
