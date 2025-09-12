<?php

namespace App\Tests;

use App\Controller\AgreementController;
use App\Entity\Agreement;
use App\Entity\User;
use App\Enum\AgreementStatus;
use App\Enum\AgreementType;
use App\Service\AgreementService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class AgreementControllerSmokeTest extends TestCase
{
    public function testGiveConsentReturns201(): void
    {
        $controller = new AgreementController(sys_get_temp_dir());
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        $user = new User();

        $agreement = (new Agreement())
            ->setUser($user)
            ->setType(AgreementType::AGB)
            ->setVersion('1.0')
            ->setConsentGiven(true)
            ->setConsentAt(new \DateTimeImmutable())
            ->setStatus(AgreementStatus::ACTIVE);
        $prop = new \ReflectionProperty($agreement, 'id');
        $prop->setAccessible(true);
        $prop->setValue($agreement, 1);

        $service = $this->createMock(AgreementService::class);
        $service->expects($this->once())
            ->method('giveConsent')
            ->with($user, AgreementType::AGB)
            ->willReturn($agreement);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $request = new Request([], [], [], [], [], [], json_encode(['type' => 'agb']));

        $response = $controller->giveConsentAction($request, $service, $security);
        $this->assertSame(201, $response->getStatusCode());
    }
}
