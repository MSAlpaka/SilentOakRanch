<?php

namespace App\Tests\Controller\Api;

use App\Entity\Booking;
use App\Entity\StallUnit;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\BookingType;
use App\Enum\ContractStatus;
use App\Enum\StallUnitStatus;
use App\Enum\StallUnitType;
use App\Enum\UserRole;
use App\Repository\AuditLogRepository;
use App\Service\ContractGenerator;
use DateTimeImmutable;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class ContractVerificationControllerTest extends WebTestCase
{
    public function testVerifyEndpointReturnsValidStatus(): void
    {
        $_ENV['WP_BRIDGE_SECRET'] = 'test-secret';
        $_SERVER['WP_BRIDGE_SECRET'] = 'test-secret';

        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        $stallUnit = (new StallUnit())
            ->setName('Audit Stall')
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
            ->setLabel('Verification Booking')
            ->setUser('audit@example.com')
            ->setPrice('200.00')
            ->setSourceUuid(Uuid::v4());
        $entityManager->persist($booking);
        $entityManager->flush();

        /** @var ContractGenerator $generator */
        $generator = $container->get(ContractGenerator::class);
        $contract = $generator->generate($booking);

        $signedContent = '%PDF-1.7 Signed Document ByteRange /Sig <<PKCS7>>';
        $signedPath = sprintf('%s/%s-signed.pdf', dirname($contract->getPath()), $contract->getId()->toRfc4122());
        file_put_contents($signedPath, $signedContent);
        $signedHash = hash('sha256', $signedContent);

        $contract
            ->setSignedPath($signedPath)
            ->setSignedHash($signedHash)
            ->setSignedAt(new DateTimeImmutable('now'))
            ->setStatus(ContractStatus::SIGNED);
        $entityManager->flush();

        $admin = (new User())
            ->setEmail('auditor@example.com')
            ->setPassword('irrelevant')
            ->setRoles(['ROLE_ADMIN'])
            ->setRole(UserRole::ADMIN)
            ->setFirstName('Audit')
            ->setLastName('User')
            ->setActive(true)
            ->setCreatedAt(new DateTimeImmutable());
        $entityManager->persist($admin);
        $entityManager->flush();

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = $container->get(JWTTokenManagerInterface::class);
        $token = $jwtManager->create($admin);

        $client->request('GET', sprintf('/api/contracts/%s/verify', $contract->getId()), server: [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('VALID', $payload['status']);
        self::assertSame($signedHash, $payload['hash']);
        self::assertSame($signedHash, $payload['signed_hash']);

        /** @var AuditLogRepository $auditLogs */
        $auditLogs = $container->get(AuditLogRepository::class);
        $entries = $auditLogs->findForEntity('CONTRACT', (string) $contract->getId());
        self::assertGreaterThanOrEqual(2, count($entries));
        self::assertSame('CONTRACT_VERIFIED', $entries[array_key_last($entries)]->getAction());
    }
}
