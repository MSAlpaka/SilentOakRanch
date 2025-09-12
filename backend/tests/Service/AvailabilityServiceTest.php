<?php

namespace App\Tests;

use App\Entity\ServiceProvider;
use App\Entity\ServiceType;
use App\Enum\ServiceProviderType;
use App\Repository\AppointmentRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class AvailabilityServiceTest extends TestCase
{
    public function testHasOverlapReturnsTrueWhenAppointmentFound(): void
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['andWhere', 'setParameter', 'getQuery'])
            ->getMock();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOneOrNullResult'])
            ->getMock();
        $query->method('getOneOrNullResult')->willReturn(new \stdClass());
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(AppointmentRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $service = new AvailabilityService($repo);
        $provider = new ServiceProvider();
        $start = new \DateTimeImmutable();
        $end = $start->modify('+1 hour');

        $this->assertTrue($service->hasOverlap($provider, $start, $end));
    }

    public function testGetAvailableSlotsSkipsOverlaps(): void
    {
        $repo = $this->createMock(AppointmentRepository::class);
        $service = $this->getMockBuilder(AvailabilityService::class)
            ->setConstructorArgs([$repo])
            ->onlyMethods(['hasOverlap'])
            ->getMock();

        $service->method('hasOverlap')->willReturnCallback(function ($provider, $start, $end) {
            return $start->format('H') === '01';
        });

        $provider = (new ServiceProvider())
            ->setName('P1')
            ->setType(ServiceProviderType::VET)
            ->setContact('c');

        $serviceType = (new ServiceType())
            ->setProviderType(ServiceProviderType::VET)
            ->setName('Checkup')
            ->setDefaultDurationMinutes(60)
            ->setBasePrice('0')
            ->setTaxable(false);

        $day = new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC'));
        $slots = $service->getAvailableSlots($provider, $serviceType, $day);

        $formatted = array_map(fn ($d) => $d->format('H:i'), $slots);
        $this->assertContains('00:00', $formatted);
        $this->assertNotContains('01:00', $formatted);
        $this->assertContains('02:00', $formatted);
    }
}
