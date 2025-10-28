<?php

namespace App\Tests\Service;

use App\Entity\Booking;
use App\Entity\StallUnit;
use App\Enum\BookingStatus;
use App\Enum\BookingType;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Enum\ContractStatus;
use App\Repository\AuditLogRepository;
use App\Service\ContractGenerator;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class ContractGeneratorTest extends KernelTestCase
{
    public function testGeneratesPdfAndPersistsContract(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        $stallUnit = (new StallUnit())
            ->setName('Test Stall')
            ->setType(StallUnitType::STALL)
            ->setArea('North')
            ->setStatus(StallUnitStatus::ACTIVE);

        $entityManager->persist($stallUnit);

        $start = new DateTimeImmutable('+1 day');
        $end = new DateTimeImmutable('+2 days');

        $booking = (new Booking())
            ->setStallUnit($stallUnit)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setDateFrom($start)
            ->setDateTo($end)
            ->setType(BookingType::SERVICE)
            ->setStatus(BookingStatus::CONFIRMED)
            ->setIsConfirmed(true)
            ->setLabel('Testvertrag')
            ->setUser('unit-test@example.com')
            ->setPrice('120.00')
            ->setSourceUuid(Uuid::v4());

        $entityManager->persist($booking);
        $entityManager->flush();

        /** @var ContractGenerator $generator */
        $generator = $container->get(ContractGenerator::class);
        $contract = $generator->generate($booking);

        self::assertSame(ContractStatus::GENERATED, $contract->getStatus());
        self::assertFileExists($contract->getPath());
        self::assertNotSame('', $contract->getHash());
        self::assertSame(hash('sha256', file_get_contents($contract->getPath())), $contract->getHash());
        self::assertNotEmpty($contract->getAuditTrail());

        /** @var AuditLogRepository $auditLogs */
        $auditLogs = $container->get(AuditLogRepository::class);
        $entries = $auditLogs->findForEntity('CONTRACT', (string) $contract->getId());
        self::assertNotEmpty($entries);
        self::assertSame('CONTRACT_GENERATED', $entries[0]->getAction());
    }
}
