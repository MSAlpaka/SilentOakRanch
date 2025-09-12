<?php

namespace App\Tests;

use App\Controller\AppointmentController;
use App\Entity\Appointment;
use App\Entity\Horse;
use App\Entity\ServiceProvider;
use App\Entity\ServiceType;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\Gender;
use App\Enum\ServiceProviderType;
use App\Enum\UserRole;
use App\Service\AppointmentService;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AppointmentControllerTest extends TestCase
{
    public function testRequestConfirmCompleteFlow(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new AppointmentController($translator);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        $user = (new User())
            ->setEmail('owner@example.com')
            ->setPassword('pw')
            ->setRoles([])
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('A')
            ->setLastName('B')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $horse1 = (new Horse())
            ->setName('Horse1')
            ->setAge(5)
            ->setBreed('Breed1')
            ->setGender(Gender::MARE)
            ->setOwner($user);
        $horse2 = (new Horse())
            ->setName('Horse2')
            ->setAge(6)
            ->setBreed('Breed2')
            ->setGender(Gender::GELDING)
            ->setOwner($user);
        $this->setId($horse1, 1);
        $this->setId($horse2, 2);

        $type1 = (new ServiceType())
            ->setProviderType(ServiceProviderType::VET)
            ->setName('Vaccination')
            ->setDefaultDurationMinutes(60)
            ->setBasePrice('20.00')
            ->setTaxable(false);
        $type2 = (new ServiceType())
            ->setProviderType(ServiceProviderType::FARRIER)
            ->setName('Shoeing')
            ->setDefaultDurationMinutes(45)
            ->setBasePrice('30.00')
            ->setTaxable(false);
        $this->setId($type1, 1);
        $this->setId($type2, 2);

        $provider1 = (new ServiceProvider())
            ->setName('Provider1')
            ->setType(ServiceProviderType::VET)
            ->setContact('p1');
        $provider2 = (new ServiceProvider())
            ->setName('Provider2')
            ->setType(ServiceProviderType::FARRIER)
            ->setContact('p2');
        $this->setId($provider1, 1);
        $this->setId($provider2, 2);

        $horses = [1 => $horse1, 2 => $horse2];
        $types = [1 => $type1, 2 => $type2];
        $providers = [1 => $provider1, 2 => $provider2];

        $horseRepo = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();
        $horseRepo->method('find')->willReturnCallback(fn ($id) => $horses[$id] ?? null);
        $typeRepo = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();
        $typeRepo->method('find')->willReturnCallback(fn ($id) => $types[$id] ?? null);
        $providerRepo = $this->getMockBuilder(EntityRepository::class)->disableOriginalConstructor()->getMock();
        $providerRepo->method('find')->willReturnCallback(fn ($id) => $providers[$id] ?? null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Horse::class, $horseRepo],
            [ServiceType::class, $typeRepo],
            [ServiceProvider::class, $providerRepo],
        ]);
        $persisted = null;
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persisted) {
            if ($entity instanceof Appointment) {
                $persisted = $entity;
                $this->setId($entity, 99);
            }
        });
        $em->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(false);

        $request = new Request([], [], [], [], [], [], json_encode([
            'horseId' => 1,
            'serviceTypeId' => 1,
            'serviceProviderId' => 1,
            'startTime' => '2024-01-01T00:00:00Z',
            'endTime' => '2024-01-01T01:00:00Z'
        ]));
        $response = $controller->create($request, $em, $security);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertNotNull($persisted);
        $this->assertSame(AppointmentStatus::REQUESTED, $persisted->getStatus());

        $controller->confirm($persisted, $em);
        $this->assertSame(AppointmentStatus::CONFIRMED, $persisted->getStatus());

        $appointmentService = new AppointmentService($em, $this->createStub(InvoiceService::class));
        $controller->complete($persisted, new Request(), $appointmentService);
        $this->assertSame(AppointmentStatus::DONE, $persisted->getStatus());
    }

    public function testCompleteWithInvoice(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $controller = new AppointmentController($translator);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        $user = (new User())
            ->setEmail('owner@example.com')
            ->setPassword('pw')
            ->setRoles([])
            ->setRole(UserRole::CUSTOMER)
            ->setFirstName('A')
            ->setLastName('B')
            ->setActive(true)
            ->setCreatedAt(new \DateTimeImmutable());

        $horse = (new Horse())
            ->setName('H')
            ->setAge(5)
            ->setBreed('B')
            ->setOwner($user);

        $type = (new ServiceType())
            ->setProviderType(ServiceProviderType::VET)
            ->setName('T')
            ->setDefaultDurationMinutes(60)
            ->setBasePrice('0')
            ->setTaxable(false);

        $appointment = (new Appointment())
            ->setHorse($horse)
            ->setOwner($user)
            ->setServiceType($type)
            ->setStartTime(new \DateTimeImmutable('2024-01-01T00:00:00Z'))
            ->setEndTime(new \DateTimeImmutable('2024-01-01T01:00:00Z'))
            ->setStatus(AppointmentStatus::CONFIRMED)
            ->setPrice('0');

        $service = $this->createMock(AppointmentService::class);
        $service->expects($this->once())
            ->method('complete')
            ->with($appointment, true);

        $request = new Request(['invoice' => '1']);
        $controller->complete($appointment, $request, $service);
    }

    private function setId(object $entity, int $id): void
    {
        $prop = new \ReflectionProperty($entity, 'id');
        $prop->setAccessible(true);
        $prop->setValue($entity, $id);
    }
}
