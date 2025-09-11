<?php

namespace App\Tests;

use App\Repository\ScaleBookingRepository;
use App\Service\ScaleSlotService;
use PHPUnit\Framework\TestCase;

class ScaleSlotServiceTest extends TestCase
{
    public function testGetAvailableSlotsGeneratesThirtyMinuteSlots(): void
    {
        $repo = $this->createMock(ScaleBookingRepository::class);
        $repo->method('existsForDateTime')->willReturn(false);

        $service = new ScaleSlotService($repo);
        $day = new \DateTimeImmutable('2024-01-01');

        $slots = $service->getAvailableSlots($day);

        $this->assertCount(48, $slots);
        $this->assertSame('2024-01-01 00:00', $slots[0]->format('Y-m-d H:i'));
        $this->assertSame('2024-01-01 23:30', $slots[47]->format('Y-m-d H:i'));
    }

    public function testIsSlotAvailableUsesRepository(): void
    {
        $repo = $this->createMock(ScaleBookingRepository::class);
        $slot = new \DateTimeImmutable('2024-01-01 10:30');

        $repo->expects($this->once())
            ->method('existsForDateTime')
            ->with($slot)
            ->willReturn(true);

        $service = new ScaleSlotService($repo);

        $this->assertFalse($service->isSlotAvailable($slot));
    }
}
