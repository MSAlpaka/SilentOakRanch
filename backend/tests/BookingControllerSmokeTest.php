<?php

namespace App\Tests;

use App\Controller\BookingController;
use App\Entity\StallUnit;
use App\Entity\User;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Enum\UserRole;
use App\Repository\BookingRepository;
use App\Repository\StallUnitRepository;
use App\Repository\PricingRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingControllerSmokeTest extends TestCase
{
    public function testInvokeReturns200(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new BookingController($translator);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        $stall = (new StallUnit())
            ->setName('Box')
            ->setType(StallUnitType::BOX)
            ->setArea('A')
            ->setStatus(StallUnitStatus::FREE);

        $stallRepo = $this->createMock(StallUnitRepository::class);
        $stallRepo->method('find')->willReturn($stall);

        $bookingRepo = $this->createMock(BookingRepository::class);
        $bookingRepo->method('hasOverlap')->willReturn(false);

        $pricingRepo = $this->createMock(PricingRuleRepository::class);
        $pricingRepo->method('findDefault')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $user = (new User())
            ->setEmail('user@example.com')
            ->setPassword('x')
            ->setRoles([])
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('A')
            ->setLastName('B')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        $request = new Request([], [], [], [], [], [], json_encode([
            'stallUnitId' => 1,
            'startDate' => '2024-01-01T00:00:00Z',
            'endDate' => '2024-01-02T00:00:00Z',
        ]));

        $response = $controller->__invoke($request, $stallRepo, $bookingRepo, $em, $security, $pricingRepo);
        $this->assertSame(200, $response->getStatusCode());
    }
}
