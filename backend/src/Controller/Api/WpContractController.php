<?php

namespace App\Controller\Api;

use App\Entity\AuditLog;
use App\Entity\Booking;
use App\Entity\Contract;
use App\Enum\ContractStatus;
use App\Repository\AuditLogRepository;
use App\Repository\BookingRepository;
use App\Repository\ContractRepository;
use App\Service\AuditLogger;
use App\Service\ContractGenerator;
use App\Service\SignatureClient;
use App\Service\SignatureValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class WpContractController extends AbstractController
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ContractRepository $contracts,
        private readonly ContractGenerator $generator,
        private readonly AuditLogger $auditLogger,
        private readonly AuditLogRepository $auditLogs,
        private readonly SignatureClient $signatureClient,
        private readonly SignatureValidator $signatureValidator,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $wpBridgeSecret
    ) {
    }

    #[Route('/api/wp/contracts', name: 'app_wp_contracts_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(200, $request->query->getInt('limit', 50)));

        $contracts = $this->contracts->createQueryBuilder('contract')
            ->addSelect('booking')
            ->join('contract.booking', 'booking')
            ->orderBy('contract.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $items = array_map(
            function (Contract $contract) use ($request): array {
                return $this->serializeContract($request, $contract);
            },
            $contracts
        );

        return $this->json([
            'ok' => true,
            'count' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('/api/wp/contracts/{bookingUuid}', name: 'app_wp_contracts_show', methods: ['GET'])]
    public function show(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = $this->resolveBooking($bookingUuid);
        if ($booking === null) {
            return $this->json(['ok' => false, 'error' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }

        $contract = $this->contracts->findOneByBooking($booking);
        if (!$contract instanceof Contract) {
            $contract = $this->generator->generate($booking);
        }

        $signedRequested = $request->query->getBoolean('signed', false);
        $signedLink = null;

        if ($signedRequested) {
            if (!$this->signatureClient->isEnabled()) {
                return $this->json(['ok' => false, 'error' => 'Signature service disabled.'], Response::HTTP_BAD_REQUEST);
            }

            if ($contract->getSignedPath() === null) {
                $result = $this->signatureClient->sign($contract);
                $contract
                    ->setSignedPath($result->getPath())
                    ->setSignedHash($result->getHash())
                    ->setSignedAt($result->getSignedAt())
                    ->setStatus(ContractStatus::SIGNED);
                $contract->appendAuditEntry('signed', $result->getHash());
                $this->auditLogger->log($contract, 'CONTRACT_SIGNED', [
                    'hash' => $result->getHash(),
                    'path' => $result->getPath(),
                ]);
                $this->entityManager->flush();
            }

            $signedLink = $this->buildDownloadUrl($request, $contract, true);
        }

        $serialized = $this->serializeContract($request, $contract);
        $downloadUrl = $serialized['contract']['download_url'];
        $serialized['contract']['signed_download_url'] = $signedLink ?: $serialized['contract']['signed_download_url'];

        return $this->json([
            'ok' => true,
            'booking_uuid' => $serialized['booking']['uuid'],
            'contract_uuid' => $serialized['contract']['uuid'],
            'status' => $serialized['contract']['status'],
            'hash' => $serialized['contract']['hash'],
            'signed' => $serialized['contract']['signed'],
            'signed_hash' => $serialized['contract']['signed_hash'],
            'download_url' => $downloadUrl,
            'signed_download_url' => $serialized['contract']['signed_download_url'],
            'verify_url' => $serialized['contract']['verify_url'],
            'audit' => $contract->getAuditTrail(),
            'audit_summary' => $serialized['contract']['audit_summary'],
            'booking' => $serialized['booking'],
            'contract' => $serialized['contract'],
        ]);
    }

    #[Route('/api/wp/contracts/{contractUuid}/verify', name: 'app_wp_contracts_verify_wp', methods: ['GET'])]
    public function verifyContract(string $contractUuid): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($contractUuid);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Invalid contract identifier.'], Response::HTTP_BAD_REQUEST);
        }

        $contract = $this->contracts->find($uuid);
        if (!$contract instanceof Contract) {
            return $this->json(['ok' => false, 'error' => 'Contract not found.'], Response::HTTP_NOT_FOUND);
        }

        $result = $this->signatureValidator->validate($contract);
        $this->auditLogger->log($contract, 'CONTRACT_VERIFIED', [
            'hash' => $result->getCalculatedHash(),
            'status' => $result->getStatus()->value,
        ]);

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'contract_uuid' => (string) $contract->getId(),
            'status' => $result->getStatus()->value,
            'hash' => $result->getCalculatedHash(),
            'expected_hash' => $result->getExpectedHash(),
            'signed_hash' => $contract->getSignedHash(),
            'signed_at' => $contract->getSignedAt()?->format(DATE_ATOM),
            'details' => $result->getDetails(),
            'received_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    #[Route('/api/wp/contracts/{contractUuid}/audit', name: 'app_wp_contracts_audit', methods: ['GET'])]
    public function auditTrail(string $contractUuid): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($contractUuid);
        } catch (\Throwable) {
            return $this->json(['ok' => false, 'error' => 'Invalid contract identifier.'], Response::HTTP_BAD_REQUEST);
        }

        $contract = $this->contracts->find($uuid);
        if (!$contract instanceof Contract) {
            return $this->json(['ok' => false, 'error' => 'Contract not found.'], Response::HTTP_NOT_FOUND);
        }

        $entries = $this->auditLogs->findForEntity('CONTRACT', (string) $contract->getId());
        $trail = array_map(static function (AuditLog $entry): array {
            return [
                'id' => (string) $entry->getId(),
                'timestamp' => $entry->getTimestamp()->format(DATE_ATOM),
                'action' => $entry->getAction(),
                'hash' => $entry->getHash(),
                'user' => $entry->getUserIdentifier(),
                'ip' => $entry->getIpAddress(),
                'meta' => $entry->getMeta(),
            ];
        }, $entries);

        return $this->json([
            'ok' => true,
            'contract_uuid' => (string) $contract->getId(),
            'count' => count($trail),
            'audit' => $trail,
        ]);
    }

    #[Route('/api/wp/contracts/{bookingUuid}/download', name: 'app_wp_contracts_download', methods: ['GET'])]
    public function download(Request $request, string $bookingUuid): Response
    {
        $booking = $this->resolveBooking($bookingUuid);
        if ($booking === null) {
            return $this->json(['ok' => false, 'error' => 'Booking not found.'], Response::HTTP_NOT_FOUND);
        }

        $contract = $this->contracts->findOneByBooking($booking);
        if (!$contract instanceof Contract) {
            return $this->json(['ok' => false, 'error' => 'Contract missing.'], Response::HTTP_NOT_FOUND);
        }

        $expires = (int) $request->query->get('expires', 0);
        $token = (string) $request->query->get('token', '');
        $variant = (string) $request->query->get('variant', 'original');

        if ($expires < time()) {
            return $this->json(['ok' => false, 'error' => 'Download link expired.'], Response::HTTP_GONE);
        }

        $expected = $this->signPayload($contract, $expires, $variant);
        if (!hash_equals($expected, $token)) {
            return $this->json(['ok' => false, 'error' => 'Invalid token.'], Response::HTTP_FORBIDDEN);
        }

        $path = $contract->getPath();
        if ($variant === 'signed') {
            $path = $contract->getSignedPath();
        }

        if ($path === null || !is_file($path)) {
            return $this->json(['ok' => false, 'error' => 'Contract file unavailable.'], Response::HTTP_NOT_FOUND);
        }

        $this->auditLogger->log($contract, 'CONTRACT_VIEWED', [
            'hash' => $variant === 'signed' ? $contract->getSignedHash() : $contract->getHash(),
            'variant' => $variant,
        ]);

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition('attachment', sprintf('vertrag-%s.pdf', $contract->getId()->toRfc4122()));

        return $response;
    }

    private function resolveBooking(string $bookingUuid): ?Booking
    {
        try {
            $uuid = Uuid::fromString($bookingUuid);
        } catch (\Throwable) {
            return null;
        }

        return $this->bookings->findOneBySourceUuid($uuid);
    }

    private function buildDownloadUrl(Request $request, Contract $contract, bool $signed): string
    {
        $expires = time() + 900;
        $variant = $signed ? 'signed' : 'original';
        $token = $this->signPayload($contract, $expires, $variant);
        $bookingUuid = $contract->getBooking()->getSourceUuid()
            ? $contract->getBooking()->getSourceUuid()->toRfc4122()
            : (string) $contract->getBooking()->getId();
        $path = $this->urlGenerator->generate('app_wp_contracts_download', [
            'bookingUuid' => $bookingUuid,
        ]);

        $query = http_build_query([
            'expires' => $expires,
            'token' => $token,
            'variant' => $variant,
        ]);

        return sprintf('%s%s?%s', $request->getSchemeAndHttpHost(), $path, $query);
    }

    private function signPayload(Contract $contract, int $expires, string $variant): string
    {
        $payload = sprintf('%s.%d.%s', $contract->getId()->toRfc4122(), $expires, $variant);

        return hash_hmac('sha256', $payload, $this->wpBridgeSecret);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContract(Request $request, Contract $contract): array
    {
        $booking = $contract->getBooking();
        $downloadUrl = $this->buildDownloadUrl($request, $contract, false);
        $signedUrl = $contract->getSignedPath() ? $this->buildDownloadUrl($request, $contract, true) : null;
        $verifyUrl = $this->urlGenerator->generate('app_contracts_verify', [
            'uuid' => (string) $contract->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $bookingUuid = $booking->getSourceUuid()?->toRfc4122();

        return [
            'booking' => [
                'id' => $booking->getId(),
                'uuid' => $bookingUuid,
                'label' => $booking->getLabel(),
                'status' => $booking->getStatus()->value,
                'horse' => $booking->getHorse()?->getName(),
                'customer' => $booking->getUser(),
                'start' => $booking->getStartDate()->format(DATE_ATOM),
                'end' => $booking->getEndDate()->format(DATE_ATOM),
            ],
            'contract' => [
                'uuid' => (string) $contract->getId(),
                'status' => $contract->getStatus()->value,
                'hash' => $contract->getHash(),
                'signed' => $contract->getStatus() === ContractStatus::SIGNED,
                'signed_hash' => $contract->getSignedHash(),
                'signed_at' => $contract->getSignedAt()?->format(DATE_ATOM),
                'updated_at' => $contract->getUpdatedAt()->format(DATE_ATOM),
                'download_url' => $downloadUrl,
                'signed_download_url' => $signedUrl,
                'verify_url' => $verifyUrl,
                'audit_summary' => $this->resolveAuditSummary($contract),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveAuditSummary(Contract $contract): ?array
    {
        $entry = $this->auditLogs->findLatestForEntity('CONTRACT', (string) $contract->getId());
        if ($entry === null) {
            return null;
        }

        $meta = $entry->getMeta();
        $status = $meta['status'] ?? null;

        return [
            'action' => $entry->getAction(),
            'timestamp' => $entry->getTimestamp()->format(DATE_ATOM),
            'user' => $entry->getUserIdentifier(),
            'hash' => $entry->getHash(),
            'status' => $status,
            'meta' => $meta,
        ];
    }
}
