<?php

namespace App\Tests;

use App\Entity\Booking;
use App\Entity\StallUnit;
use App\Enum\BookingStatus;
use App\Enum\BookingType;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Repository\BookingRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class WpBridgeTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private BookingRepository $bookingRepository;

    private string $bridgeKey;

    private string $bridgeSecret;

    private string $webhookToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->bookingRepository = $container->get(BookingRepository::class);
        $this->bridgeKey = (string) $container->getParameter('wp_bridge_key');
        $this->bridgeSecret = (string) $container->getParameter('wp_bridge_secret');
        $this->webhookToken = (string) ($_SERVER['WORDPRESS_WEBHOOK_TOKEN'] ?? $_ENV['WORDPRESS_WEBHOOK_TOKEN'] ?? '');

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata !== []) {
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->entityManager)) {
            $this->entityManager->close();
        }

        static::ensureKernelShutdown();
    }

    public function testSignedRequestCreatesBooking(): void
    {
        $stallUnit = $this->createStallUnit('arena');

        $payload = [
            'uuid' => 'a1c0c19f-53d3-4bbf-99d2-cf6a5b8ee0d8',
            'resource' => 'arena',
            'slot_start' => '2025-01-10T09:00:00+00:00',
            'slot_end' => '2025-01-10T10:00:00+00:00',
            'price' => '75.00',
            'status' => 'confirmed',
            'email' => 'rider@example.com',
            'name' => 'Ranch Visitor',
            'horse_name' => 'Starlight',
            'stall_unit_id' => $stallUnit->getId(),
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = $this->signedHeaders('POST', '/api/wp/bookings', $body);

        $this->client->request('POST', '/api/wp/bookings', server: $headers, content: $body);
        $response = $this->client->getResponse();

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['ok']);
        self::assertSame('confirmed', $data['status']);

        $this->entityManager->clear();
        $bookings = $this->bookingRepository->findAll();
        self::assertCount(1, $bookings);

        $booking = $bookings[0];
        self::assertInstanceOf(Booking::class, $booking);
        self::assertSame($stallUnit->getId(), $booking->getStallUnit()->getId());
        self::assertEquals(new DateTimeImmutable($payload['slot_start']), $booking->getStartDate());
        self::assertEquals(new DateTimeImmutable($payload['slot_end']), $booking->getEndDate());
        self::assertSame('75.00', $booking->getPrice());
        self::assertSame('rider@example.com', $booking->getUser());
        self::assertSame(BookingStatus::CONFIRMED, $booking->getStatus());
        self::assertSame(BookingType::SERVICE, $booking->getType());
        self::assertTrue($booking->isConfirmed());
        self::assertSame('WP Arena - Starlight', $booking->getLabel());
    }

    public function testRequestWithoutHmacHeadersIsRejected(): void
    {
        $payload = [
            'uuid' => 'b9fcdb21-0180-4e46-90b7-2aa4fd441ccf',
            'resource' => 'arena',
            'slot_start' => '2025-01-10T09:00:00+00:00',
            'slot_end' => '2025-01-10T10:00:00+00:00',
            'price' => '75.00',
            'status' => 'pending',
            'stall_unit_id' => 1,
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/wp/bookings', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->webhookToken),
        ], content: $body);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(0, $this->bookingRepository->count([]));
    }

    public function testRequestWithInvalidSignatureIsRejected(): void
    {
        $payload = [
            'uuid' => '989dc216-8ff0-4caf-8f2f-41e2bd5e16a6',
            'resource' => 'arena',
            'slot_start' => '2025-01-10T09:00:00+00:00',
            'slot_end' => '2025-01-10T10:00:00+00:00',
            'price' => '75.00',
            'status' => 'pending',
            'stall_unit_id' => 1,
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = $this->signedHeaders('POST', '/api/wp/bookings', $body, signatureOverride: 'invalid-signature');

        $this->client->request('POST', '/api/wp/bookings', server: $headers, content: $body);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(0, $this->bookingRepository->count([]));
    }

    public function testRequestWithExpiredTimestampIsRejected(): void
    {
        $payload = [
            'uuid' => '4bc03f5d-5b94-4a38-bbd8-97e1879d3c65',
            'resource' => 'arena',
            'slot_start' => '2025-01-10T09:00:00+00:00',
            'slot_end' => '2025-01-10T10:00:00+00:00',
            'price' => '75.00',
            'status' => 'pending',
            'stall_unit_id' => 1,
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = $this->signedHeaders('POST', '/api/wp/bookings', $body, new DateTimeImmutable('-10 minutes'));

        $this->client->request('POST', '/api/wp/bookings', server: $headers, content: $body);

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(0, $this->bookingRepository->count([]));
    }

    private function createStallUnit(string $resourceName): StallUnit
    {
        $stallUnit = (new StallUnit())
            ->setName(ucfirst($resourceName))
            ->setType(StallUnitType::BOX)
            ->setArea('Main barn')
            ->setStatus(StallUnitStatus::FREE)
            ->setMonthlyRent('500.00');

        $this->entityManager->persist($stallUnit);
        $this->entityManager->flush();

        return $stallUnit;
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(
        string $method,
        string $path,
        string $body,
        ?DateTimeImmutable $timestamp = null,
        ?string $signatureOverride = null
    ): array {
        $timestamp ??= new DateTimeImmutable('now');
        $formatted = $timestamp->format(DateTimeInterface::ATOM);
        $payload = sprintf('%s|%s|%s|%s', strtoupper($method), $path, $formatted, $body);
        $signature = $signatureOverride ?? hash_hmac('sha256', $payload, $this->bridgeSecret);

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SOR_KEY' => $this->bridgeKey,
            'HTTP_X_SOR_DATE' => $formatted,
            'HTTP_X_SOR_SIGNATURE' => $signature,
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->webhookToken),
        ];
    }
}
