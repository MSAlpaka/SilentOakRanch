<?php

namespace App\Tests;

use App\Entity\Horse;
use App\Entity\User;
use App\Enum\ScaleBookingType;
use App\Enum\UserRole;
use App\Service\QrCodeGenerator;
use App\Service\ScaleBookingService;
use App\Service\ScaleSlotService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ScaleBookingServiceTest extends TestCase
{
    public function testCalculatePrice(): void
    {
        $service = new ScaleBookingService(
            $this->createStub(ScaleSlotService::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(QrCodeGenerator::class),
        );

        $this->assertSame('10.00', $service->calculatePrice(ScaleBookingType::SINGLE));
        $this->assertSame('45.00', $service->calculatePrice(ScaleBookingType::PACKAGE));
        $this->assertSame('20.00', $service->calculatePrice(ScaleBookingType::PREMIUM));
        $slot = new \DateTimeImmutable('2024-01-01 16:00');
        $this->assertSame('18.00', $service->calculatePrice(ScaleBookingType::DYNAMIC, $slot));
    }

    public function testCreateBookingGeneratesQrToken(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $slotService = $this->createMock(ScaleSlotService::class);
        $slot = new \DateTimeImmutable('2024-01-01 10:00');
        $slotService->expects($this->once())->method('isSlotAvailable')->with($slot)->willReturn(true);

        $qr = $this->createMock(QrCodeGenerator::class);
        $qr->expects($this->once())->method('generate')->with($this->isType('string'));

        $service = new ScaleBookingService($slotService, $em, $qr);

        $owner = (new User())
            ->setEmail('a@b.c')
            ->setPassword('pw')
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('A')
            ->setLastName('B')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $horse = (new Horse())
            ->setName('Star')
            ->setAge(5)
            ->setBreed('Arab')
            ->setOwner($owner);

        $booking = $service->createBooking($horse, $owner, $slot, ScaleBookingType::SINGLE);

        $this->assertNotEmpty($booking->getQrToken());
        $this->assertSame('10.00', $booking->getPrice());
    }
}
