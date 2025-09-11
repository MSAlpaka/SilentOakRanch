<?php

namespace App\Tests;

use App\Controller\ScaleBookingController;
use App\Service\ScaleSlotService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class ScaleBookingControllerTest extends TestCase
{
    public function testSlotsReturns200(): void
    {
        $controller = new ScaleBookingController();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        $slotService = $this->createMock(ScaleSlotService::class);
        $slotService->method('getAvailableSlots')->willReturn([
            new \DateTimeImmutable('2024-01-01 10:00:00'),
        ]);

        $request = new Request(['day' => '2024-01-01']);
        $response = $controller->slots($request, $slotService);
        $this->assertSame(200, $response->getStatusCode());
    }
}
