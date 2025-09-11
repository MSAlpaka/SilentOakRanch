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
use PHPUnit\Framework\Attributes\DataProvider;

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

    #[DataProvider('bookingPriceProvider')]
    public function testCreateBookingSetsCorrectPrice(
        ScaleBookingType $type,
        string $expectedPrice,
        string $slotTime
    ): void {
        $em = $this->createStub(EntityManagerInterface::class);

        $slotService = $this->createStub(ScaleSlotService::class);
        $slotService->method('isSlotAvailable')->willReturn(true);

        $qr = $this->createStub(QrCodeGenerator::class);

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

        $slot = new \DateTimeImmutable($slotTime);
        $booking = $service->createBooking($horse, $owner, $slot, $type);

        $this->assertSame($expectedPrice, $booking->getPrice());
    }

    public static function bookingPriceProvider(): iterable
    {
        yield [ScaleBookingType::SINGLE, '10.00', '2024-01-01 10:00'];
        yield [ScaleBookingType::PACKAGE, '45.00', '2024-01-01 10:00'];
        yield [ScaleBookingType::PREMIUM, '20.00', '2024-01-01 10:00'];
        yield [ScaleBookingType::DYNAMIC, '18.00', '2024-01-01 16:00'];
    }
}
