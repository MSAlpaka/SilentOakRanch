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
use DateTimeInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class ContractApiTest extends WebTestCase
{
    public function testContractEndpointReturnsDownloadLink(): void
    {
        $_ENV['WP_BRIDGE_SECRET'] = 'test-secret';
        $_SERVER['WP_BRIDGE_SECRET'] = 'test-secret';
        $_ENV['WP_BRIDGE_KEY'] = 'test-key';
        $_SERVER['WP_BRIDGE_KEY'] = 'test-key';
        $_ENV['WORDPRESS_WEBHOOK_TOKEN'] = 'test-webhook-token';
        $_SERVER['WORDPRESS_WEBHOOK_TOKEN'] = 'test-webhook-token';
        $_ENV['ENABLE_SIGNATURES'] = '1';
        $_SERVER['ENABLE_SIGNATURES'] = '1';
        $databaseFile = sys_get_temp_dir() . '/sor_contracts_test.sqlite';
        if (file_exists($databaseFile)) {
            unlink($databaseFile);
        }
        $databaseDsn = 'sqlite:///' . $databaseFile;
        $_ENV['DATABASE_URL'] = $databaseDsn;
        $_SERVER['DATABASE_URL'] = $databaseDsn;
        putenv('WP_BRIDGE_SECRET=test-secret');
        putenv('WP_BRIDGE_KEY=test-key');
        putenv('WORDPRESS_WEBHOOK_TOKEN=test-webhook-token');
        putenv('ENABLE_SIGNATURES=1');
        putenv('DATABASE_URL=' . $databaseDsn);

        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $stallUnit = (new StallUnit())
            ->setName('API Stall')
            ->setType(StallUnitType::BOX)
            ->setArea('Ost')
            ->setStatus(StallUnitStatus::FREE);

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

        $contractPath = '/api/wp/contracts/' . $booking->getSourceUuid()->toRfc4122();
        $client->request('GET', $contractPath, [], [], $this->buildHeaders('GET', $contractPath));
        $response = $client->getResponse();

        self::assertResponseIsSuccessful();
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertSame($contract->getHash(), $payload['hash']);
        self::assertNotEmpty($payload['download_url']);
        self::assertArrayHasKey('verify_url', $payload);
        self::assertNotEmpty($payload['verify_url']);
        self::assertArrayHasKey('audit_summary', $payload);
        self::assertIsArray($payload['audit_summary']);
        self::assertSame('CONTRACT_GENERATED', $payload['audit_summary']['action']);

        $verifyPath = '/api/wp/contracts/' . $payload['contract_uuid'] . '/verify';
        $client->request('GET', $verifyPath, [], [], $this->buildHeaders('GET', $verifyPath));
        $verifyResponse = $client->getResponse();
        self::assertResponseIsSuccessful();
        $verifyPayload = json_decode($verifyResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($verifyPayload['ok']);
        self::assertSame($payload['contract_uuid'], $verifyPayload['contract_uuid']);
        self::assertArrayHasKey('status', $verifyPayload);

        $auditPath = '/api/wp/contracts/' . $payload['contract_uuid'] . '/audit';
        $client->request('GET', $auditPath, [], [], $this->buildHeaders('GET', $auditPath));
        $auditResponse = $client->getResponse();
        self::assertResponseIsSuccessful();
        $auditPayload = json_decode($auditResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($auditPayload['ok']);
        self::assertSame($payload['contract_uuid'], $auditPayload['contract_uuid']);
        self::assertGreaterThanOrEqual(1, $auditPayload['count']);
        self::assertIsArray($auditPayload['audit']);

        $downloadUrl = $payload['download_url'];
        $parts = parse_url($downloadUrl);
        self::assertIsArray($parts);
        $client->request('GET', $parts['path'] . '?' . ($parts['query'] ?? ''));
        $downloadResponse = $client->getResponse();
        self::assertSame(200, $downloadResponse->getStatusCode());
        self::assertSame('application/pdf', $downloadResponse->headers->get('content-type'));

        $indexPath = '/api/wp/contracts';
        $client->request('GET', $indexPath, [], [], $this->buildHeaders('GET', $indexPath));
        $listResponse = $client->getResponse();
        self::assertResponseIsSuccessful();
        $listPayload = json_decode($listResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($listPayload['ok']);
        self::assertSame(1, $listPayload['count']);
        self::assertIsArray($listPayload['items']);
        self::assertSame($payload['contract_uuid'], $listPayload['items'][0]['contract']['uuid']);
        self::assertSame($payload['verify_url'], $listPayload['items'][0]['contract']['verify_url']);
        self::assertArrayHasKey('audit_summary', $listPayload['items'][0]['contract']);

        /** @var ContractRepository $contracts */
        $contracts = $container->get(ContractRepository::class);
        $stored = $contracts->findOneByBooking($booking);
        self::assertNotNull($stored);
        self::assertSame($contract->getHash(), $stored->getHash());
}

    /**
     * @param string $method HTTP method
     * @param string $path   Request path (including leading slash)
     * @param string $body   Request body
     *
     * @return array<string, string>
     */
    private function buildHeaders(string $method, string $path, string $body = ''): array
    {
        $timestamp = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
        $payload = sprintf("%s\n%s\n%s\n%s", strtoupper($method), $path, $timestamp, $body);
        $signature = hash_hmac('sha256', $payload, $_ENV['WP_BRIDGE_SECRET']);

        return [
            'HTTP_X_SOR_KEY' => $_ENV['WP_BRIDGE_KEY'],
            'HTTP_X_SOR_DATE' => $timestamp,
            'HTTP_X_SOR_SIGNATURE' => $signature,
            'HTTP_AUTHORIZATION' => 'Bearer ' . $_ENV['WORDPRESS_WEBHOOK_TOKEN'],
        ];
    }
}
