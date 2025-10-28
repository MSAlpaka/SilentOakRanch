<?php

namespace App\Controller\Api;

use App\Entity\Booking;
use App\Entity\Contract;
use App\Enum\ContractStatus;
use App\Repository\BookingRepository;
use App\Repository\ContractRepository;
use App\Service\ContractGenerator;
use App\Service\SignatureClient;
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
        private readonly SignatureClient $signatureClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $wpBridgeSecret
    ) {
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
                $this->entityManager->flush();
            }

            $signedLink = $this->buildDownloadUrl($request, $contract, true);
        }

        $downloadUrl = $this->buildDownloadUrl($request, $contract, false);

        return $this->json([
            'ok' => true,
            'booking_uuid' => $booking->getSourceUuid() ? $booking->getSourceUuid()->toRfc4122() : null,
            'contract_uuid' => (string) $contract->getId(),
            'status' => $contract->getStatus()->value,
            'hash' => $contract->getHash(),
            'signed' => $contract->getStatus() === ContractStatus::SIGNED,
            'signed_hash' => $contract->getSignedHash(),
            'download_url' => $downloadUrl,
            'signed_download_url' => $signedLink,
            'audit' => $contract->getAuditTrail(),
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
}
