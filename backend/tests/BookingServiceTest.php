<?php

namespace App\Tests;

use App\Entity\Package;
use App\Service\BookingService;
use App\Service\CalendarService;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingServiceTest extends TestCase
{
    public function testCreateBookingPersistsAndReturnsBooking(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $calendar = $this->createMock(CalendarService::class);
        $calendar->method('isRangeFree')->willReturn(true);

        $mail = $this->createMock(MailService::class);
        $mail->expects($this->once())->method('sendBookingConfirmation');
        $mail->expects($this->once())->method('sendInvoiceDraft');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $service = new BookingService($em, $calendar, $mail, $translator);

        $package = (new Package())
            ->setName('Pkg')
            ->setDuration(3)
            ->setDailyRate('5.00');

        $start = new \DateTimeImmutable('2024-01-01');
        $booking = $service->createBooking($package, $start);

        $this->assertSame('15.00', $booking->getPrice());
    }
}
