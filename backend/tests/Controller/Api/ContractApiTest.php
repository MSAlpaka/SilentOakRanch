<?php

namespace App\Tests\Controller\Api;

use App\Entity\Booking;
use App\Entity\StallUnit;
use App\Enum\BookingStatus;
use App\Enum\BookingType;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Repository\ContractRepository;
use App\Service\ContractGenerator;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class ContractApiTest extends WebTestCase
{
    public function testContractEndpointReturnsDownloadLink(): void
    {
        $_ENV['WP_BRIDGE_SECRET'] = 'test-secret';
        $_SERVER['WP_BRIDGE_SECRET'] = 'test-secret';

        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        $stallUnit = (new StallUnit())
            ->setName('API Stall')
            ->setType(StallUnitType::STALL)
            ->setArea('Ost')
            ->setStatus(StallUnitStatus::ACTIVE);

        $entityManager->persist($stallUnit);

        $start = new DateTimeImmutable('+3 days');
        $end = new DateTimeImmutable('+4 days');

        $booking = (new Booking())
            ->setStallUnit($stallUnit)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setDateFrom($start)
            ->setDateTo($end)
            ->setType(BookingType::SERVICE)
            ->setStatus(BookingStatus::CONFIRMED)
            ->setIsConfirmed(true)
            ->setLabel('API Vertrag')
            ->setUser('api@example.com')
            ->setPrice('150.00')
            ->setSourceUuid(Uuid::v4());

        $entityManager->persist($booking);
        $entityManager->flush();

        /** @var ContractGenerator $generator */
        $generator = $container->get(ContractGenerator::class);
        $contract = $generator->generate($booking);

        $client->request('GET', '/api/wp/contracts/' . $booking->getSourceUuid()->toRfc4122());
        $response = $client->getResponse();

        self::assertResponseIsSuccessful();
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertSame($contract->getHash(), $payload['hash']);
        self::assertNotEmpty($payload['download_url']);

        $downloadUrl = $payload['download_url'];
        $parts = parse_url($downloadUrl);
        self::assertIsArray($parts);
        $client->request('GET', $parts['path'] . '?' . ($parts['query'] ?? ''));
        $downloadResponse = $client->getResponse();
        self::assertSame(200, $downloadResponse->getStatusCode());
        self::assertSame('application/pdf', $downloadResponse->headers->get('content-type'));

        /** @var ContractRepository $contracts */
        $contracts = $container->get(ContractRepository::class);
        $stored = $contracts->findOneByBooking($booking);
        self::assertNotNull($stored);
        self::assertSame($contract->getHash(), $stored->getHash());
    }
}
