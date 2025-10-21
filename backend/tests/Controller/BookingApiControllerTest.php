<?php

namespace App\Tests\Controller;

use App\RanchBooking\Entity\Booking;
use App\RanchBooking\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

class BookingApiControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private BookingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = 'test-key';
        $_ENV['RANCH_API_KEY'] = $apiKey;
        $_SERVER['RANCH_API_KEY'] = $apiKey;
        putenv('RANCH_API_KEY=' . $apiKey);

        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(BookingRepository::class);

        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata !== []) {
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
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

    public function testPostCreatesBooking(): void
    {
        $uuid = Uuid::v4()->toRfc4122();
        $payload = $this->basePayload($uuid);

        $response = $this->postBooking($payload);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['ok']);
        self::assertSame($uuid, $data['uuid']);

        $this->entityManager->clear();
        $stored = $this->repository->find(Uuid::fromString($uuid));
        self::assertInstanceOf(Booking::class, $stored);
        self::assertSame('25.00', $stored->getPrice());
    }

    public function testPatchUpdatesStatus(): void
    {
        $uuid = Uuid::v4()->toRfc4122();
        $this->postBooking($this->basePayload($uuid));

        $client = static::createClient();
        $client->request(
            'PATCH',
            '/api/bookings/' . $uuid . '/status',
            server: $this->authorizedHeaders(),
            content: json_encode(['status' => Booking::STATUS_CONFIRMED], JSON_THROW_ON_ERROR)
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(Booking::STATUS_CONFIRMED, $data['status']);

        $this->entityManager->clear();
        $stored = $this->repository->find(Uuid::fromString($uuid));
        self::assertInstanceOf(Booking::class, $stored);
        self::assertSame(Booking::STATUS_CONFIRMED, $stored->getStatus());
    }

    public function testInvalidKeyRejected(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/bookings',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer wrong-key'],
            content: json_encode($this->basePayload(Uuid::v4()->toRfc4122()), JSON_THROW_ON_ERROR)
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testDuplicateUuidUpdatesExistingBooking(): void
    {
        $uuid = Uuid::v4()->toRfc4122();
        $this->postBooking($this->basePayload($uuid));

        $updatedPayload = $this->basePayload($uuid, [
            'price' => '30.00',
            'status' => Booking::STATUS_PAID,
        ]);

        $response = $this->postBooking($updatedPayload);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->entityManager->clear();
        $stored = $this->repository->find(Uuid::fromString($uuid));
        self::assertInstanceOf(Booking::class, $stored);
        self::assertSame('30.00', $stored->getPrice());
        self::assertSame(Booking::STATUS_PAID, $stored->getStatus());
    }

    public function testOverlapRejected(): void
    {
        $uuid = Uuid::v4()->toRfc4122();
        $this->postBooking($this->basePayload($uuid, [
            'slot_start' => '2025-01-01T10:00:00+00:00',
            'slot_end' => '2025-01-01T11:00:00+00:00',
        ]));

        $client = static::createClient();
        $client->request(
            'POST',
            '/api/bookings',
            server: $this->authorizedHeaders(),
            content: json_encode($this->basePayload(Uuid::v4()->toRfc4122(), [
                'slot_start' => '2025-01-01T10:30:00+00:00',
                'slot_end' => '2025-01-01T11:30:00+00:00',
            ]), JSON_THROW_ON_ERROR)
        );

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postBooking(array $payload): Response
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/bookings',
            server: $this->authorizedHeaders(),
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );

        return $client->getResponse();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function basePayload(string $uuid, array $overrides = []): array
    {
        $payload = [
            'uuid' => $uuid,
            'resource' => Booking::RESOURCE_SOLEKAMMER,
            'slot_start' => '2025-01-01T09:00:00+00:00',
            'slot_end' => '2025-01-01T10:00:00+00:00',
            'price' => '25.00',
            'status' => Booking::STATUS_PENDING,
            'name' => 'Ranch Visitor',
            'email' => 'guest@example.com',
            'source' => Booking::SOURCE_WEBSITE,
        ];

        foreach ($overrides as $key => $value) {
            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function authorizedHeaders(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer test-key',
        ];
    }
}
