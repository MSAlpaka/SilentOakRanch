<?php

namespace App\Tests;

use App\Entity\Appointment;
use App\Enum\AppointmentStatus;
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
}

